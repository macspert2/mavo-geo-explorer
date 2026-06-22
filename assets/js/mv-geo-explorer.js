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

			// Drill-down state (Europe ⇄ a single country's regions, e.g. France).
			this.drillSlug = null;
			this.drillGeoCache = {};

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
			const featureCollection = this.drillSlug ? this.drillGeoCache[this.drillSlug] : this.geojson;
			if (featureCollection) {
				this.renderMap(featureCollection);
			}
		}

		renderMap(featureCollection) {
			const rect = this.mapWrap.getBoundingClientRect();
			const width = Math.max(280, Math.round(rect.width));
			const height = Math.max(320, Math.round(width * 0.86));

			const svg = d3.select(this.svg);
			svg.attr('viewBox', '0 0 ' + width + ' ' + height).attr('preserveAspectRatio', 'xMidYMid meet');

			const projection = d3.geoNaturalEarth1();
			projection.fitSize([width - 12, height - 12], featureCollection);
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
						self.selectPlace(self.slugFor(d));
					}
				})
				.on('keydown', function (event, d) {
					if ((event.key === 'Enter' || event.key === ' ') && self.hasPosts(d)) {
						event.preventDefault();
						self.selectPlace(self.slugFor(d));
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
			this.panelEl.appendChild(this.el('p', 'mv-geo-explorer__panel-count', this.countLabel(place.post_count)));

			if (place.drilldown && this.index.drilldowns && this.index.drilldowns[slug]) {
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
			const places = Object.keys(this.index.places)
				.map((slug) => Object.assign({ slug }, this.index.places[slug]))
				.filter((place) => place.post_count > 0 && (drillSlug ? place.parent === drillSlug : !place.parent))
				.sort((a, b) => b.post_count - a.post_count);

			if (this.listHeadingEl) {
				const current = drillSlug && this.placeFor(drillSlug);
				this.listHeadingEl.textContent = current ? current.label : this.config.strings.list_heading;
			}

			this.listItemsEl.innerHTML = '';
			places.forEach((place) => {
				const li = document.createElement('li');
				li.className = 'mv-geo-explorer__list-item';

				const link = document.createElement('a');
				link.href = place.url;
				link.textContent = place.label;
				link.addEventListener('mouseenter', () => this.setHover(place.slug));
				link.addEventListener('mouseleave', () => this.clearHover());
				link.addEventListener('focus', () => this.setHover(place.slug));
				link.addEventListener('blur', () => this.clearHover());

				li.appendChild(link);
				if (this.config.showCounts) {
					li.appendChild(this.el('span', 'mv-geo-explorer__list-count', this.countLabel(place.post_count)));
				}
				this.listItemsEl.appendChild(li);
			});
		}

		selectPlace(slug) {
			this.selected = slug;
			this.updateShapeClasses();
			this.renderPanel(slug);
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

		async drillInto(countrySlug) {
			const drilldown = this.index && this.index.drilldowns && this.index.drilldowns[countrySlug];
			if (!drilldown) {
				return;
			}

			if (!this.drillGeoCache[countrySlug]) {
				try {
					const res = await fetch(this.config.geoBaseUrl + drilldown.geo_file, { credentials: 'omit' });
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

		renderBreadcrumb() {
			if (!this.breadcrumbEl) {
				return;
			}
			this.breadcrumbEl.innerHTML = '';

			if (!this.drillSlug) {
				this.breadcrumbEl.hidden = true;
				return;
			}

			const back = document.createElement('button');
			back.type = 'button';
			back.className = 'mv-geo-explorer__breadcrumb-back';
			back.textContent = this.config.strings.back_to_europe;
			back.addEventListener('click', () => this.drillBack());
			this.breadcrumbEl.appendChild(back);

			const place = this.placeFor(this.drillSlug);
			if (place) {
				this.breadcrumbEl.appendChild(this.el('span', 'mv-geo-explorer__breadcrumb-current', place.label));
			}

			this.breadcrumbEl.hidden = false;
		}

		// -------------------------------------------------------------
		// Helpers
		// -------------------------------------------------------------

		activeShapeMap() {
			if (this.drillSlug && this.index && this.index.drilldowns && this.index.drilldowns[this.drillSlug]) {
				return this.index.drilldowns[this.drillSlug].shape_map;
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
			const classes = ['mv-geo-shape'];
			classes.push(this.hasPosts(d) ? 'mv-geo-shape--has-posts' : 'mv-geo-shape--empty');
			if (slug && slug === this.hovered) {
				classes.push('mv-geo-shape--hover');
			}
			if (slug && slug === this.selected) {
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
			this.tooltipEl.textContent = place.label + ' — ' + this.countLabel(place.post_count);
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
