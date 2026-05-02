/**
 * MinimalCode Theme Toggle
 * Handles dark/light mode switching with localStorage persistence
 */

(function() {
  'use strict';

  // Theme management
  const ThemeManager = {
    storageKey: 'minimalcode-theme',
    
    init() {
      this.applyStoredTheme();
      this.attachEventListeners();
    },
    
    getStoredTheme() {
      return localStorage.getItem(this.storageKey);
    },
    
    getPreferredTheme() {
      // Default to light. Dark mode is opt-in via the toggle (or ⌘+Shift+D).
      // System color-scheme preference is intentionally ignored.
      return this.getStoredTheme() || 'light';
    },
    
    setTheme(theme) {
      document.documentElement.setAttribute('data-theme', theme);
      localStorage.setItem(this.storageKey, theme);
      
      // Update meta theme-color for mobile browsers
      const metaThemeColor = document.querySelector('meta[name="theme-color"]');
      if (metaThemeColor) {
        metaThemeColor.setAttribute('content', theme === 'dark' ? '#0d1117' : '#ffffff');
      } else {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = theme === 'dark' ? '#0d1117' : '#ffffff';
        document.head.appendChild(meta);
      }
    },
    
    toggleTheme() {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      this.setTheme(newTheme);
      
      // Add a subtle animation
      document.body.style.transition = 'none';
      setTimeout(() => {
        document.body.style.transition = '';
      }, 10);
    },
    
    applyStoredTheme() {
      const theme = this.getPreferredTheme();
      this.setTheme(theme);
    },
    
    attachEventListeners() {
      // Theme toggle button
      const toggleButton = document.querySelector('.theme-toggle');
      if (toggleButton) {
        toggleButton.addEventListener('click', () => this.toggleTheme());
      }
      
      // Keyboard shortcut: Ctrl/Cmd + Shift + D
      document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
          e.preventDefault();
          this.toggleTheme();
        }
      });
    }
  };

  // Smooth scroll for anchor links
  const SmoothScroll = {
    init() {
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', (e) => {
          const href = anchor.getAttribute('href');
          if (href === '#') return;
          
          const target = document.querySelector(href);
          if (target) {
            e.preventDefault();
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            
            // Update URL without jumping
            if (history.pushState) {
              history.pushState(null, null, href);
            }
          }
        });
      });
    }
  };

  // Copy code button for pre blocks
  const CodeCopyButton = {
    init() {
      document.querySelectorAll('pre').forEach(pre => {
        const button = document.createElement('button');
        button.className = 'copy-code-button';
        button.textContent = 'Copy';
        button.setAttribute('aria-label', 'Copy code to clipboard');
        
        button.addEventListener('click', async () => {
          const code = pre.querySelector('code')?.textContent || pre.textContent;
          
          try {
            await navigator.clipboard.writeText(code);
            button.textContent = 'Copied!';
            button.classList.add('copied');
            
            setTimeout(() => {
              button.textContent = 'Copy';
              button.classList.remove('copied');
            }, 2000);
          } catch (err) {
            console.error('Failed to copy code:', err);
            button.textContent = 'Failed';
            setTimeout(() => {
              button.textContent = 'Copy';
            }, 2000);
          }
        });
        
        pre.style.position = 'relative';
        pre.appendChild(button);
      });
    }
  };

  // Search Modal (⌘K) — live results via WP REST /wp/v2/search.
  const SearchModal = {
    modal: null,
    input: null,
    resultsEl: null,
    results: [],
    activeIndex: -1,
    debounceTimer: null,
    requestSeq: 0,

    init() {
      this.createModal();
      this.attachEventListeners();
    },

    createModal() {
      const modal = document.createElement('div');
      modal.className = 'search-modal';
      modal.innerHTML = `
        <div class="search-modal-overlay"></div>
        <div class="search-modal-content" role="dialog" aria-modal="true" aria-label="Search">
          <div class="search-modal-header">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" class="search-modal-input" placeholder="Search posts…" autocomplete="off" spellcheck="false" aria-controls="search-modal-results" aria-autocomplete="list">
            <kbd class="search-modal-kbd">ESC</kbd>
          </div>
          <ul id="search-modal-results" class="search-modal-results" role="listbox" aria-label="Search results"></ul>
          <div class="search-modal-hints">
            <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
            <span><kbd>⏎</kbd> open</span>
            <span><kbd>esc</kbd> close</span>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      this.modal = modal;
      this.input = modal.querySelector('.search-modal-input');
      this.resultsEl = modal.querySelector('.search-modal-results');
    },

    open() {
      this.modal.classList.add('active');
      this.input.value = '';
      this.results = [];
      this.activeIndex = -1;
      this.renderResults('');
      this.input.focus();
      document.body.style.overflow = 'hidden';
    },

    close() {
      this.modal.classList.remove('active');
      document.body.style.overflow = '';
    },

    fallbackSubmit() {
      const query = this.input.value.trim();
      if (query) {
        window.location.href = `/?s=${encodeURIComponent(query)}`;
      }
    },

    async fetchResults(query) {
      if (!query) {
        this.results = [];
        this.activeIndex = -1;
        this.renderResults(query);
        return;
      }
      // Prefer wp.apiFetch (handles nonce). Fall back to plain fetch.
      const path = `/wp/v2/search?search=${encodeURIComponent(query)}&per_page=8&type=post&_embed=1`;
      const seq = ++this.requestSeq;
      try {
        let data;
        if (window.wp && window.wp.apiFetch) {
          data = await window.wp.apiFetch({ path });
        } else {
          const res = await fetch(`/wp-json${path}`, { credentials: 'same-origin' });
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          data = await res.json();
        }
        // Drop stale responses if a newer query already fired.
        if (seq !== this.requestSeq) return;
        this.results = Array.isArray(data) ? data : [];
        this.activeIndex = this.results.length ? 0 : -1;
        this.renderResults(query);
      } catch (err) {
        if (seq !== this.requestSeq) return;
        this.results = [];
        this.activeIndex = -1;
        this.renderError(err);
      }
    },

    renderResults(query) {
      if (!this.resultsEl) return;

      if (!query) {
        this.resultsEl.innerHTML = `<li class="search-modal-empty">Type to search posts.</li>`;
        return;
      }

      if (!this.results.length) {
        this.resultsEl.innerHTML = `<li class="search-modal-empty">No results for &ldquo;${this.escape(query)}&rdquo;.</li>`;
        return;
      }

      const html = this.results.map((r, i) => {
        const title = this.highlight(r.title || '', query);
        const subtitle = this.escape(r.subtype || r.type || '');
        const selected = i === this.activeIndex ? 'true' : 'false';
        return `<li class="search-modal-result" role="option" aria-selected="${selected}" data-index="${i}" data-url="${this.escape(r.url || '#')}">
          <span class="search-modal-result-title">${title}</span>
          <span class="search-modal-result-meta">${subtitle}</span>
        </li>`;
      }).join('');
      this.resultsEl.innerHTML = html;
    },

    renderError(err) {
      this.resultsEl.innerHTML = `<li class="search-modal-empty">Search unavailable. Press ⏎ to try a full-page search.</li>`;
    },

    highlight(text, query) {
      const safe = this.escape(text);
      if (!query) return safe;
      // Escape regex special chars in the query, then wrap matches in <mark>.
      const pattern = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      try {
        return safe.replace(new RegExp(`(${pattern})`, 'ig'), '<mark>$1</mark>');
      } catch (_) {
        return safe;
      }
    },

    escape(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    setActive(newIndex) {
      if (!this.results.length) return;
      const max = this.results.length - 1;
      if (newIndex < 0) newIndex = max;
      if (newIndex > max) newIndex = 0;
      this.activeIndex = newIndex;
      const items = this.resultsEl.querySelectorAll('.search-modal-result');
      items.forEach((el, i) => {
        const sel = i === this.activeIndex;
        el.setAttribute('aria-selected', sel ? 'true' : 'false');
        if (sel) el.scrollIntoView({ block: 'nearest' });
      });
    },

    openActive() {
      if (this.activeIndex < 0) {
        this.fallbackSubmit();
        return;
      }
      const result = this.results[this.activeIndex];
      if (result && result.url) {
        window.location.href = result.url;
      } else {
        this.fallbackSubmit();
      }
    },

    attachEventListeners() {
      // ⌘K / Ctrl+K to open
      document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
          e.preventDefault();
          this.open();
        }
      });

      // ESC to close
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.modal.classList.contains('active')) {
          this.close();
        }
      });

      // Debounced live query.
      this.input.addEventListener('input', () => {
        const query = this.input.value.trim();
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => this.fetchResults(query), 150);
      });

      // Keyboard navigation in input.
      this.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          this.openActive();
        } else if (e.key === 'ArrowDown') {
          e.preventDefault();
          this.setActive(this.activeIndex + 1);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          this.setActive(this.activeIndex - 1);
        }
      });

      // Click on a result.
      this.resultsEl.addEventListener('click', (e) => {
        const li = e.target.closest('.search-modal-result');
        if (!li) return;
        const idx = parseInt(li.dataset.index, 10);
        if (!isNaN(idx)) {
          this.activeIndex = idx;
          this.openActive();
        }
      });

      // Mouse hover updates active row so Enter feels right.
      this.resultsEl.addEventListener('mousemove', (e) => {
        const li = e.target.closest('.search-modal-result');
        if (!li) return;
        const idx = parseInt(li.dataset.index, 10);
        if (!isNaN(idx) && idx !== this.activeIndex) this.setActive(idx);
      });

      // Click overlay to close
      this.modal.querySelector('.search-modal-overlay').addEventListener('click', () => {
        this.close();
      });

      // Search button in header
      const searchBtn = document.querySelector('.search-trigger');
      if (searchBtn) {
        searchBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.open();
        });
      }
    }
  };

  // Reading progress bar
  const ReadingProgress = {
    init() {
      if (!document.body.classList.contains('single-post')) return;

      const progressBar = document.createElement('div');
      progressBar.className = 'reading-progress';
      progressBar.innerHTML = '<div class="reading-progress-bar"></div>';
      document.body.appendChild(progressBar);

      const bar = progressBar.querySelector('.reading-progress-bar');

      window.addEventListener('scroll', () => {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight - windowHeight;
        const scrolled = (window.scrollY / documentHeight) * 100;
        bar.style.width = Math.min(scrolled, 100) + '%';
      });
    }
  };

  // Table of Contents
  const TableOfContents = {
    headings: [],
    tocList: null,

    init() {
      this.tocList = document.getElementById('toc-list');
      if (!this.tocList) return;

      const content = document.querySelector('.entry-content');
      if (!content) return;

      const headings = content.querySelectorAll('h2, h3');
      if (headings.length === 0) {
        // Hide TOC sidebar if no headings
        const tocSidebar = document.querySelector('.post-toc-sidebar');
        if (tocSidebar) tocSidebar.style.display = 'none';
        return;
      }

      this.buildTOC(headings);
      this.observeHeadings();
    },

    buildTOC(headings) {
      headings.forEach((heading, index) => {
        // Generate ID if not present
        if (!heading.id) {
          heading.id = this.slugify(heading.textContent) || `section-${index}`;
        }

        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = `#${heading.id}`;
        a.textContent = heading.textContent;
        a.className = heading.tagName === 'H3' ? 'toc-h3' : 'toc-h2';

        li.appendChild(a);
        this.tocList.appendChild(li);

        this.headings.push({
          id: heading.id,
          element: heading,
          link: a
        });
      });
    },

    slugify(text) {
      return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .substring(0, 50);
    },

    observeHeadings() {
      const observerOptions = {
        rootMargin: '-80px 0px -70% 0px',
        threshold: 0
      };

      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            this.setActiveLink(entry.target.id);
          }
        });
      }, observerOptions);

      this.headings.forEach(({ element }) => {
        observer.observe(element);
      });
    },

    setActiveLink(activeId) {
      this.headings.forEach(({ id, link }) => {
        if (id === activeId) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
    }
  };

  // Initialize everything when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      ThemeManager.init();
      SmoothScroll.init();
      SearchModal.init();
      TableOfContents.init();

      // Wait a bit for Prism to finish highlighting
      setTimeout(() => {
        CodeCopyButton.init();
      }, 100);

      ReadingProgress.init();
    });
  } else {
    ThemeManager.init();
    SmoothScroll.init();
    SearchModal.init();
    TableOfContents.init();
    setTimeout(() => {
      CodeCopyButton.init();
    }, 100);
    ReadingProgress.init();
  }
})();

