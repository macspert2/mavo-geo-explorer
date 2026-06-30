(function () {
	'use strict';

	// Sentinel id/slug for the World view's merged "Europe" shape (built at
	// asset-build time by unioning europe-countries.simple.geojson into one
	// feature with this exact properties.id). Not a real place in the index
	// — it has no term/url/post_count of its own, it's a navigation shortcut
	// back to the Europe view, handled as a special case wherever a real
	// place would normally be looked up.
	const EUROPE_SHAPE_ID = 'EUROPE';
	const EUROPE_SLUG = '__europe__';

	class MVGeoExplorer {
		constructor(root, config) {
			this.root = root;
			this.config = config;
			this.svg = root.querySelector('.mv-geo-explorer__svg');
			this.mapWrap = root.querySelector('.mv-geo-explorer__map-wrap');
			this.loadingEl = root.querySelector('.mv-geo-explorer__loading');
			this.breadcrumbEl = root.querySelector('.mv-geo-explorer__breadcrumb');
			this.panelEl = root.querySelector('.mv-geo-explorer__panel');
			this.listItemsEl = root.querySelector('.mv-geo-explorer__list-items');
			this.listHeadingEl = root.querySelector('.mv-geo-explorer__list-heading');

			this.index = null;
			this.geojson = null;
			this.selected = null;
			this.hovered = null;
			this.tooltipEl = null;

			// Drill-down state (Europe/World ⇄ a single country's regions, e.g. France).
			this.drillSlug = null;
			this.drillGeoCache = {};

			// Zoom state: 'europe' (default) or 'world'. config.defaultView
			// can also be 'regions' (start pre-drilled into one country) — that
			// doesn't change the *backing* baseView, just sets drillSlug once
			// the index is loaded (see resolveDefaultRegionSlug()).
			this.baseView = config.defaultView === 'world' ? 'world' : 'europe';
			this.worldGeojson = null;

			this._onResize = this.debounce(() => this.render(), 150);
		}

		async init() {
			try {
				await this.loadIndex();
			} catch (err) {
				this.showError();
				return;
			}

			this.hideLoading();
			this.renderPanel(null);
			this.renderBreadcrumb();
			if (this.config.showList) {
				this.renderList();
			}

			try {
				const startDrillSlug = this.resolveDefaultRegionSlug();
				if (startDrillSlug) {
					await this.drillInto(startDrillSlug);
				} else {
					await this.loadGeoJson(this.baseView);
					this.render();
				}
				window.addEventListener('resize', this._onResize);
			} catch (err) {
				this.showMapError();
			}
		}

		/**
		 * default_view="regions" passes the target country through as an
		 * alpha-3 shape id (config.defaultRegionShapeId) rather than a slug —
		 * slugs are language-specific and not knowable server-side at
		 * shortcode-render time. Resolves it against the now-loaded index;
		 * returns null (silently falling back to the default Europe/World
		 * view) if it doesn't resolve to anything drillable in this
		 * language, e.g. a language whose region data hasn't been
		 * backfilled yet, mirroring canDrillInto()'s own graceful checks.
		 */
		resolveDefaultRegionSlug() {
			if (this.config.defaultView !== 'regions' || !this.config.defaultRegionShapeId || !this.index) {
				return null;
			}
			const slug = Object.keys(this.index.places).find(
				(key) => this.index.places[key].map_shape_id === this.config.defaultRegionShapeId
			);
			return slug && this.canDrillInto(slug) ? slug : null;
		}

		async loadIndex() {
			const res = await fetch(this.config.indexUrl, { credentials: 'omit' });
			if (!res.ok) {
				throw new Error('mv-geo-explorer: failed to load index (' + res.status + ')');
			}
			this.index = await res.json();
		}

		/**
		 * Loads (and caches) one of the two top-level geometry files. 'world'
		 * is cached on this.worldGeojson since zoomToWorld() may call this
		 * repeatedly as the visitor toggles views; 'europe' is only ever
		 * loaded once, at init().
		 */
		async loadGeoJson(view) {
			if (view === 'world') {
				if (this.worldGeojson) {
					return;
				}
				const res = await fetch(this.config.worldGeoUrl, { credentials: 'omit' });
				if (!res.ok) {
					throw new Error('mv-geo-explorer: failed to load world geojson (' + res.status + ')');
				}
				this.worldGeojson = await res.json();
				return;
			}
			const res = await fetch(this.config.geoUrl, { credentials: 'omit' });
			if (!res.ok) {
				throw new Error('mv-geo-explorer: failed to load geojson (' + res.status + ')');
			}
			this.geojson = await res.json();
		}

		render() {
			let featureCollection;
			if (this.drillSlug) {
				featureCollection = this.drillGeoCache[this.drillSlug];
			} else if (this.baseView === 'world') {
				featureCollection = this.worldGeojson;
			} else {
				featureCollection = this.geojson;
			}
			if (featureCollection) {
				this.renderMap(featureCollection);
			}
		}

		/**
		 * Zooms the projection to fit only the shapes that actually have
		 * posts, instead of the whole collection — makes better use of the
		 * available space when only a handful of countries (or regions) are
		 * active, especially on EN/DE where far fewer places have content
		 * than FR. Falls back to the full collection if nothing is active at
		 * all (e.g. before the first rebuild), so the map never collapses to
		 * a degenerate/empty fit.
		 */
		fitTarget(featureCollection) {
			const active = featureCollection.features.filter((d) => this.hasPosts(d));
			return active.length > 0 ? { type: 'FeatureCollection', features: active } : featureCollection;
		}

		renderMap(featureCollection) {
			const rect = this.mapWrap.getBoundingClientRect();
			const width = Math.max(280, Math.round(rect.width));
			const height = Math.max(320, Math.round(width * 0.86));

			const svg = d3.select(this.svg);
			svg.attr('viewBox', '0 0 ' + width + ' ' + height).attr('preserveAspectRatio', 'xMidYMid meet');

			const projection = d3.geoNaturalEarth1();
			projection.fitSize([width - 12, height - 12], this.fitTarget(featureCollection));
			const path = d3.geoPath(projection);

			const self = this;
			svg
				.selectAll('path.mv-geo-shape')
				.data(featureCollection.features, (d) => d.properties.id)
				.join('path')
				.attr('class', (d) => self.shapeClass(d))
				.attr('d', path)
				.attr('tabindex', (d) => (self.hasPosts(d) ? 0 : -1))
				.attr('role', (d) => (self.hasPosts(d) ? 'button' : 'img'))
				.attr('aria-label', (d) => self.ariaLabel(d))
				.on('mouseenter', function (event, d) {
					self.setHover(self.slugFor(d));
					const place = self.placeFor(self.slugFor(d));
					if (place) {
						self.showTooltip(place, event);
					}
				})
				.on('mousemove', function (event, d) {
					self.moveTooltip(event);
				})
				.on('mouseleave', function () {
					self.clearHover();
				})
				.on('click', function (event, d) {
					if (self.hasPosts(d)) {
						self.selectOrDrill(self.slugFor(d));
					}
				})
				.on('keydown', function (event, d) {
					if ((event.key === 'Enter' || event.key === ' ') && self.hasPosts(d)) {
						event.preventDefault();
						self.selectOrDrill(self.slugFor(d));
					}
				});
		}

		renderPanel(slug) {
			const strings = this.config.strings;

			this.panelEl.innerHTML = '';

			// The merged Europe shape on the World map isn't a real place (no
			// term/url/post_count) — it's a shortcut back to the Europe view,
			// so its panel is just a heading + an explicit "View Europe"
			// button (mirrors the drilldown "View regions" button below: a
			// discoverable single click, with the map's own click-twice as a
			// bonus shortcut for people who don't look at the panel).
			if (slug === EUROPE_SLUG) {
				this.panelEl.appendChild(this.el('h2', 'mv-geo-explorer__panel-title', strings.europe_label));
				const europeBtn = document.createElement('button');
				europeBtn.type = 'button';
				europeBtn.className = 'mv-geo-explorer__panel-drill';
				europeBtn.textContent = strings.view_europe;
				europeBtn.addEventListener('click', () => this.zoomToEurope());
				this.panelEl.appendChild(europeBtn);
				return;
			}

			const place = this.placeFor(slug);

			if (!place) {
				this.panelEl.appendChild(this.el('h2', 'mv-geo-explorer__panel-title', strings.default_title));
				this.panelEl.appendChild(this.el('p', 'mv-geo-explorer__panel-text', strings.default_text));
				return;
			}

			this.panelEl.appendChild(this.el('h2', 'mv-geo-explorer__panel-title', place.label));
			if (this.config.showCounts) {
				this.panelEl.appendChild(this.el('p', 'mv-geo-explorer__panel-count', this.countLabel(place.post_count)));
			}

			if (this.canDrillInto(slug)) {
				const drillBtn = document.createElement('button');
				drillBtn.type = 'button';
				drillBtn.className = 'mv-geo-explorer__panel-drill';
				drillBtn.textContent = strings.view_regions;
				drillBtn.addEventListener('click', () => this.drillInto(slug));
				this.panelEl.appendChild(drillBtn);
			}

			// When the panel already lists every article for this place
			// (post_count <= maxPosts), the "Populaires :" subtitle and the
			// "Tous les articles" CTA are redundant — the list isn't a
			// selection of the most-read, it's all of them, and there's
			// nothing more to see on the tag page. Show both only when there
			// are more articles than fit in the panel.
			const hasMore = place.post_count > this.config.maxPosts;

			if (this.config.showPosts && Array.isArray(place.top_posts) && place.top_posts.length) {
				if (hasMore) {
					this.panelEl.appendChild(this.el('p', 'mv-geo-explorer__panel-subheading', strings.top_articles));
				}
				const list = document.createElement('ul');
				list.className = 'mv-geo-explorer__panel-posts';
				place.top_posts.slice(0, this.config.maxPosts).forEach((post) => {
					list.appendChild(this.renderPostItem(post));
				});
				this.panelEl.appendChild(list);
			}

			if (hasMore) {
				const link = document.createElement('a');
				link.className = 'mv-geo-explorer__panel-cta';
				link.href = place.url;
				link.textContent = strings.view_articles;
				this.panelEl.appendChild(link);
			}
		}

		renderPostItem(post) {
			const li = document.createElement('li');
			li.className = 'mv-geo-explorer__panel-post';

			const link = document.createElement('a');
			link.href = post.url;

			if (post.thumb) {
				const img = document.createElement('img');
				img.src = post.thumb;
				img.alt = '';
				img.loading = 'lazy';
				link.appendChild(img);
			}

			link.appendChild(this.el('span', 'mv-geo-explorer__panel-post-title', post.title));
			li.appendChild(link);
			return li;
		}

		renderList() {
			if (!this.listItemsEl || !this.index) {
				return;
			}

			const drillSlug = this.drillSlug;
			const self = this;
			const places = Object.keys(this.index.places)
				.map((slug) => Object.assign({ slug }, this.index.places[slug]))
				.filter((place) => {
					if (place.post_count <= 0) {
						return false;
					}
					if (drillSlug) {
						return place.parent === drillSlug;
					}
					if (place.parent) {
						return false; // a region/child place, not relevant at a top-level view
					}
					if (self.baseView === 'world') {
						// World list excludes only countries already shown
						// individually on the Europe map (they're represented by
						// the single merged Europe shape/link instead). Anything
						// not in Europe's curated set — including an overseas
						// territory like Guadeloupe that the geotagger resolved
						// as its own place distinct from mainland France — still
						// shows here, with no special-casing needed.
						return !Object.prototype.hasOwnProperty.call(self.index.shape_map, place.map_shape_id);
					}
					// Default Europe view: only countries actually shown on the Europe map —
					// keeps countries-with-posts from other continents out of this list
					// (they belong in the World list instead).
					return Object.prototype.hasOwnProperty.call(self.index.shape_map, place.map_shape_id);
				})
				.sort((a, b) => {
					// World's country list reads better alphabetically (it's a
					// long, otherwise-unordered list of everywhere-but-Europe);
					// Europe's and any drilldown's region list stay ranked by
					// post_count as before.
					if (self.baseView === 'world' && !drillSlug) {
						return a.label.localeCompare(b.label, self.config.lang);
					}
					return b.post_count - a.post_count;
				});

			if (this.listHeadingEl) {
				const current = drillSlug && this.placeFor(drillSlug);
				const heading = current ? current.label : this.baseView === 'world' ? this.config.strings.list_heading_world : this.config.strings.list_heading;
				this.listHeadingEl.textContent = heading;
			}

			const shapeMap = this.activeShapeMap();

			this.listItemsEl.innerHTML = '';
			places.forEach((place) => {
				const li = document.createElement('li');
				li.className = 'mv-geo-explorer__list-item';

				const link = document.createElement('a');
				link.href = place.url;
				link.textContent = place.label;
				link.dataset.slug = place.slug;
				link.addEventListener('mouseenter', () => this.setHover(place.slug));
				link.addEventListener('mouseleave', () => this.clearHover());
				link.addEventListener('focus', () => this.setHover(place.slug));
				link.addEventListener('blur', () => this.clearHover());
				link.addEventListener('click', (event) => this.handleListLinkClick(place.slug, event));

				// Some places (e.g. Canarias for Spain, same reasoning as
				// Russia on the Europe map) deliberately have no shape in the
				// current view's geometry — too far away to fit without
				// zooming the whole map out. Still a real, working link; just
				// flagged so hovering/selecting it doesn't look broken when
				// nothing highlights on the map.
				if (!place.map_shape_id || shapeMap[place.map_shape_id] !== place.slug) {
					link.classList.add('mv-geo-explorer__list-link--off-map');
					link.title = this.config.strings.not_on_map;
				}

				li.appendChild(link);
				if (this.config.showCounts) {
					li.appendChild(this.el('span', 'mv-geo-explorer__list-count', this.countLabel(place.post_count)));
				}
				this.listItemsEl.appendChild(li);
			});

			this.updateListSelection();
			this.renderListEuropeLink();
		}

		/**
		 * World view only: a heading-level link below the country list that
		 * goes straight to the Europe view in a single click — unlike every
		 * other place link, there's no preview/second-click step, since
		 * "Europe" isn't a single destination with its own panel content,
		 * it's a whole different view of the map. Lazily creates the element
		 * once and just toggles it after that, same pattern as the tooltip.
		 */
		renderListEuropeLink() {
			if (!this.listItemsEl) {
				return;
			}
			const show = this.baseView === 'world' && !this.drillSlug;
			if (!this.listEuropeLinkEl) {
				if (!show) {
					return;
				}
				const heading = document.createElement('h3');
				heading.className = 'mv-geo-explorer__list-europe';
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'mv-geo-explorer__list-europe-link';
				button.addEventListener('click', () => this.zoomToEurope());
				heading.appendChild(button);
				this.listItemsEl.insertAdjacentElement('afterend', heading);
				this.listEuropeLinkEl = heading;
				this.listEuropeButtonEl = button;
			}
			this.listEuropeButtonEl.textContent = this.config.strings.europe_label;
			this.listEuropeLinkEl.hidden = !show;
		}

		/**
		 * Mirrors the map shapes' click behaviour (Section: selectOrDrill) —
		 * first click previews (shows the panel, with its own explicit "View
		 * articles" button as the discoverable way to go further), a second
		 * click on the *same*, already-selected link lets the real navigation
		 * through instead of intercepting it. No JS at all (or a crawler that
		 * doesn't run it) just sees a normal `<a href>` and follows it
		 * directly — nothing here depends on JS to reach the tag page.
		 */
		handleListLinkClick(slug, event) {
			if (slug === this.selected) {
				return;
			}
			event.preventDefault();
			this.selectPlace(slug);
		}

		/**
		 * Keeps the list in sync with whichever place is selected (via the
		 * map *or* the list itself) without rebuilding the list — rebuilding
		 * on every selection would drop keyboard focus right after the user
		 * activates a link.
		 */
		updateListSelection() {
			if (!this.listItemsEl) {
				return;
			}
			this.listItemsEl.querySelectorAll('a[data-slug]').forEach((link) => {
				const isSelected = link.dataset.slug === this.selected;
				link.classList.toggle('mv-geo-explorer__list-link--selected', isSelected);
				if (isSelected) {
					link.setAttribute('aria-current', 'true');
				} else {
					link.removeAttribute('aria-current');
				}
			});
		}

		selectPlace(slug) {
			this.selected = slug;
			this.updateShapeClasses();
			this.renderPanel(slug);
			this.updateListSelection();
		}

		/**
		 * Clicking/activating a shape that's already selected drills into it
		 * directly (if it supports drilldown) instead of just re-selecting it —
		 * a shortcut for the panel's "view regions" button. The merged Europe
		 * shape (World view) gets the same two-click shortcut, just leading to
		 * the Europe view instead of a region drilldown.
		 */
		selectOrDrill(slug) {
			if (slug && slug === this.selected) {
				if (slug === EUROPE_SLUG) {
					this.zoomToEurope();
					return;
				}
				if (this.canDrillInto(slug)) {
					this.drillInto(slug);
					return;
				}
			}
			this.selectPlace(slug);
		}

		clearHover() {
			this.hovered = null;
			this.hideTooltip();
			this.updateShapeClasses();
		}

		setHover(slug) {
			this.hovered = slug;
			this.updateShapeClasses();
		}

		// -------------------------------------------------------------
		// Drill-down (Europe ⇄ a country's regions)
		// -------------------------------------------------------------

		/**
		 * True when `slug` both has region data AND the shortcode instance
		 * allows drilling at all (`show_drilldown="0"` disables this site-wide
		 * for the instance — e.g. used on EN/DE pages where the region maps
		 * aren't wanted yet).
		 */
		canDrillInto(slug) {
			if (!this.config.showDrilldown) {
				return false;
			}
			const place = this.placeFor(slug);
			return Boolean(place && place.drilldown && this.index.drilldowns && this.index.drilldowns[slug]);
		}

		async drillInto(countrySlug) {
			if (!this.canDrillInto(countrySlug)) {
				return;
			}
			const drilldown = this.index.drilldowns[countrySlug];

			if (!this.drillGeoCache[countrySlug]) {
				try {
					const url = this.config.geoBaseUrl + drilldown.geo_file + '?ver=' + encodeURIComponent(this.config.assetVersion || '');
					const res = await fetch(url, { credentials: 'omit' });
					if (!res.ok) {
						throw new Error('mv-geo-explorer: failed to load drilldown geojson (' + res.status + ')');
					}
					this.drillGeoCache[countrySlug] = await res.json();
				} catch (err) {
					return; // keep the current view rather than show a broken one
				}
			}

			this.drillSlug = countrySlug;
			this.afterViewChange();
		}

		drillBack() {
			this.drillSlug = null;
			this.afterViewChange();
		}

		/**
		 * Zooms out from the default Europe view to a worldwide map. Lazily
		 * fetches+caches world-countries.simple.geojson on first use, same
		 * pattern as drillInto() for a country's regions.
		 */
		async zoomToWorld() {
			if (this.baseView === 'world') {
				return;
			}
			try {
				await this.loadGeoJson('world');
			} catch (err) {
				return;
			}
			this.baseView = 'world';
			this.drillSlug = null;
			this.afterViewChange();
		}

		zoomToEurope() {
			this.baseView = 'europe';
			this.drillSlug = null;
			this.afterViewChange();
		}

		afterViewChange() {
			this.selected = null;
			this.hovered = null;
			this.hideTooltip();
			this.renderBreadcrumb();
			this.render();
			this.renderPanel(null);
			if (this.config.showList) {
				this.renderList();
			}
		}

		/**
		 * Three states, always one of them visible (never fully hidden — the
		 * default Europe state shows a forward "view world" link so zooming
		 * out is discoverable, not just zooming back in once you've used it):
		 *   1. Drilled into a country's regions: back-to-(europe|world) + country name.
		 *   2. Zoomed out to World (no drill): back-to-Europe only.
		 *   3. Default Europe (no drill): forward "view world" link only.
		 */
		renderBreadcrumb() {
			if (!this.breadcrumbEl) {
				return;
			}
			this.breadcrumbEl.innerHTML = '';
			const strings = this.config.strings;

			if (this.drillSlug) {
				const backLabel = this.baseView === 'world' ? strings.back_to_world : strings.back_to_europe;
				this.breadcrumbEl.appendChild(this.makeBreadcrumbButton(backLabel, 'mv-geo-explorer__breadcrumb-back', () => this.drillBack()));

				const place = this.placeFor(this.drillSlug);
				if (place) {
					this.breadcrumbEl.appendChild(this.el('span', 'mv-geo-explorer__breadcrumb-current', place.label));
				}
			} else if (this.baseView === 'world') {
				this.breadcrumbEl.appendChild(this.makeBreadcrumbButton(strings.back_to_europe, 'mv-geo-explorer__breadcrumb-back', () => this.zoomToEurope()));
			} else {
				this.breadcrumbEl.appendChild(this.makeBreadcrumbButton(strings.view_world, 'mv-geo-explorer__breadcrumb-forward', () => this.zoomToWorld()));
			}

			this.breadcrumbEl.hidden = false;
		}

		makeBreadcrumbButton(label, className, onClick) {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = className;
			button.textContent = label;
			button.addEventListener('click', onClick);
			return button;
		}

		// -------------------------------------------------------------
		// Helpers
		// -------------------------------------------------------------

		activeShapeMap() {
			if (this.drillSlug && this.index && this.index.drilldowns && this.index.drilldowns[this.drillSlug]) {
				return this.index.drilldowns[this.drillSlug].shape_map;
			}
			if (this.baseView === 'world') {
				return (this.index && this.index.world_shape_map) || {};
			}
			return (this.index && this.index.shape_map) || {};
		}

		updateShapeClasses() {
			if (!this.svg) {
				return;
			}
			const self = this;
			d3.select(this.svg)
				.selectAll('path.mv-geo-shape')
				.attr('class', (d) => self.shapeClass(d));
		}

		shapeClass(d) {
			const slug = this.slugFor(d);
			const isHovered = Boolean(slug) && slug === this.hovered;
			const isSelected = Boolean(slug) && slug === this.selected;

			const classes = ['mv-geo-shape'];
			classes.push(this.hasPosts(d) ? 'mv-geo-shape--has-posts' : 'mv-geo-shape--empty');
			// Drilldown-capable countries get a slightly darker resting shade so
			// they stand out as "explore further" — hover/selected still take
			// over the fill entirely, same as any other country. Hidden
			// entirely when show_drilldown="0" (canDrillInto() checks that),
			// since the shade would otherwise advertise a feature that's
			// disabled for this shortcode instance.
			if (!isHovered && !isSelected && (this.canDrillInto(slug) || slug === EUROPE_SLUG)) {
				classes.push('mv-geo-shape--drilldown');
			}
			if (isHovered) {
				classes.push('mv-geo-shape--hover');
			}
			if (isSelected) {
				classes.push('mv-geo-shape--selected');
			}
			return classes.join(' ');
		}

		slugFor(d) {
			if (!this.index || !d || !d.properties) {
				return null;
			}
			if (d.properties.id === EUROPE_SHAPE_ID) {
				return EUROPE_SLUG;
			}
			return this.activeShapeMap()[d.properties.id] || null;
		}

		placeFor(slug) {
			if (slug === EUROPE_SLUG) {
				// Not a real place — no term/url/post_count, just a label to
				// show in a tooltip/panel. hasPosts()/ariaLabel() special-case
				// this slug separately rather than relying on post_count here.
				return { type: 'continent', label: this.config.strings.europe_label, url: null };
			}
			if (!slug || !this.index) {
				return null;
			}
			return this.index.places[slug] || null;
		}

		hasPosts(d) {
			if (this.slugFor(d) === EUROPE_SLUG) {
				return true; // always clickable — it's a navigation shortcut, not a destination with its own count
			}
			const place = this.placeFor(this.slugFor(d));
			return Boolean(place && place.post_count > 0);
		}

		ariaLabel(d) {
			const slug = this.slugFor(d);
			const place = this.placeFor(slug);
			if (!place) {
				return '';
			}
			if (slug === EUROPE_SLUG) {
				return place.label;
			}
			return place.label + ', ' + this.countLabel(place.post_count);
		}

		countLabel(count) {
			const strings = this.config.strings;
			const word = count === 1 ? strings.article_singular : strings.article_plural;
			return count + ' ' + word;
		}

		showTooltip(place, event) {
			if (!this.tooltipEl) {
				this.tooltipEl = document.createElement('div');
				this.tooltipEl.className = 'mv-geo-explorer__tooltip';
				this.mapWrap.appendChild(this.tooltipEl);
			}
			const hasCount = this.config.showCounts && typeof place.post_count === 'number';
			this.tooltipEl.textContent = hasCount ? place.label + ' — ' + this.countLabel(place.post_count) : place.label;
			this.tooltipEl.hidden = false;
			this.moveTooltip(event);
		}

		moveTooltip(event) {
			if (!this.tooltipEl || this.tooltipEl.hidden || !event) {
				return;
			}
			const wrapRect = this.mapWrap.getBoundingClientRect();
			this.tooltipEl.style.left = event.clientX - wrapRect.left + 12 + 'px';
			this.tooltipEl.style.top = event.clientY - wrapRect.top + 12 + 'px';
		}

		hideTooltip() {
			if (this.tooltipEl) {
				this.tooltipEl.hidden = true;
			}
		}

		hideLoading() {
			if (this.loadingEl) {
				this.loadingEl.hidden = true;
			}
		}

		showError() {
			this.hideLoading();
			if (this.mapWrap) {
				this.mapWrap.innerHTML = '<p class="mv-geo-explorer__error">' + this.escapeText(this.config.strings.error) + '</p>';
			}
		}

		showMapError() {
			this.hideLoading();
			const error = document.createElement('p');
			error.className = 'mv-geo-explorer__error';
			error.textContent = this.config.strings.error;
			this.mapWrap.appendChild(error);
		}

		el(tag, className, text) {
			const node = document.createElement(tag);
			node.className = className;
			node.textContent = text;
			return node;
		}

		escapeText(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}

		debounce(fn, wait) {
			let timeout;
			return function () {
				clearTimeout(timeout);
				timeout = setTimeout(fn, wait);
			};
		}
	}

	function bootstrap() {
		document.querySelectorAll('[data-mv-geo-explorer]').forEach((root) => {
			let config = {};
			try {
				config = JSON.parse(root.dataset.config || '{}');
			} catch (err) {
				return;
			}
			new MVGeoExplorer(root, config).init();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootstrap);
	} else {
		bootstrap();
	}
})();
