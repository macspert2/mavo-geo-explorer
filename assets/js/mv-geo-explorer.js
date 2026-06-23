(function () {
	'use strict';

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

			// Zoom state: 'europe' (default) or 'world'.
			this.baseView = 'europe';
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
				await this.loadGeoJson(this.config.defaultView);
				this.render();
				window.addEventListener('resize', this._onResize);
			} catch (err) {
				this.showMapError();
			}
		}

		async loadIndex() {
			const res = await fetch(this.config.indexUrl, { credentials: 'omit' });
			if (!res.ok) {
				throw new Error('mv-geo-explorer: failed to load index (' + res.status + ')');
			}
			this.index = await res.json();
		}

		async loadGeoJson(view) {
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
			const place = this.placeFor(slug);
			const strings = this.config.strings;

			this.panelEl.innerHTML = '';

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

			if (this.config.showPosts && Array.isArray(place.top_posts) && place.top_posts.length) {
				this.panelEl.appendChild(this.el('p', 'mv-geo-explorer__panel-subheading', strings.top_articles));
				const list = document.createElement('ul');
				list.className = 'mv-geo-explorer__panel-posts';
				place.top_posts.slice(0, this.config.maxPosts).forEach((post) => {
					list.appendChild(this.renderPostItem(post));
				});
				this.panelEl.appendChild(list);
			}

			const link = document.createElement('a');
			link.className = 'mv-geo-explorer__panel-cta';
			link.href = place.url;
			link.textContent = strings.view_articles;
			this.panelEl.appendChild(link);
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
						return true; // full worldwide country list
					}
					// Default Europe view: only countries actually shown on the Europe map —
					// keeps countries-with-posts from other continents out of this list
					// (they belong in the World list instead).
					return Object.prototype.hasOwnProperty.call(self.index.shape_map, place.map_shape_id);
				})
				.sort((a, b) => b.post_count - a.post_count);

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
		 * a shortcut for the panel's "view regions" button.
		 */
		selectOrDrill(slug) {
			if (slug && slug === this.selected && this.canDrillInto(slug)) {
				this.drillInto(slug);
				return;
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
			if (!this.worldGeojson) {
				try {
					const res = await fetch(this.config.worldGeoUrl, { credentials: 'omit' });
					if (!res.ok) {
						throw new Error('mv-geo-explorer: failed to load world geojson (' + res.status + ')');
					}
					this.worldGeojson = await res.json();
				} catch (err) {
					return;
				}
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
			if (!isHovered && !isSelected && this.canDrillInto(slug)) {
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
			return this.activeShapeMap()[d.properties.id] || null;
		}

		placeFor(slug) {
			if (!slug || !this.index) {
				return null;
			}
			return this.index.places[slug] || null;
		}

		hasPosts(d) {
			const place = this.placeFor(this.slugFor(d));
			return Boolean(place && place.post_count > 0);
		}

		ariaLabel(d) {
			const place = this.placeFor(this.slugFor(d));
			if (!place) {
				return '';
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
			this.tooltipEl.textContent = this.config.showCounts ? place.label + ' — ' + this.countLabel(place.post_count) : place.label;
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
