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
      const storedTheme = this.getStoredTheme();
      if (storedTheme) {
        return storedTheme;
      }
      
      // Check system preference
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
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
      
      // Listen for system theme changes
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        // Only apply if user hasn't manually set a preference
        if (!this.getStoredTheme()) {
          this.setTheme(e.matches ? 'dark' : 'light');
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

  // Search Modal (⌘K)
  const SearchModal = {
    modal: null,
    input: null,

    init() {
      this.createModal();
      this.attachEventListeners();
    },

    createModal() {
      const modal = document.createElement('div');
      modal.className = 'search-modal';
      modal.innerHTML = `
        <div class="search-modal-overlay"></div>
        <div class="search-modal-content">
          <div class="search-modal-header">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" class="search-modal-input" placeholder="Search posts..." autocomplete="off">
            <kbd class="search-modal-kbd">ESC</kbd>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      this.modal = modal;
      this.input = modal.querySelector('.search-modal-input');
    },

    open() {
      this.modal.classList.add('active');
      this.input.value = '';
      this.input.focus();
      document.body.style.overflow = 'hidden';
    },

    close() {
      this.modal.classList.remove('active');
      document.body.style.overflow = '';
    },

    submit() {
      const query = this.input.value.trim();
      if (query) {
        window.location.href = `/?s=${encodeURIComponent(query)}`;
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

      // Enter to submit
      this.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          this.submit();
        }
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

  // Initialize everything when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      ThemeManager.init();
      SmoothScroll.init();
      SearchModal.init();

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
    setTimeout(() => {
      CodeCopyButton.init();
    }, 100);
    ReadingProgress.init();
  }
})();

