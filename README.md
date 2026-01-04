# MinimalCode WordPress Theme

A clean, minimalist WordPress theme designed for technical blogs with dark mode support and code highlighting.

## Features

- 🎨 **Clean & Minimal Design** - Distraction-free reading experience
- 🌓 **Dark Mode** - Automatic system preference detection with manual toggle
- 💻 **Code Highlighting** - Built-in Prism.js syntax highlighting for multiple languages
- 📱 **Fully Responsive** - Mobile-first design that works on all devices
- ⚡ **Performance Optimized** - Fast loading with minimal dependencies
- 📖 **Reading Progress** - Visual progress bar on single posts
- 📋 **Copy Code** - One-click code copying from code blocks
- ♿ **Accessible** - WCAG compliant with semantic HTML
- 🎯 **SEO Ready** - Clean markup and proper heading structure
- 🔧 **Customizable** - WordPress Customizer integration for easy setup

## Installation

1. Download or clone this repository into your WordPress themes directory:
   ```bash
   cd wp-content/themes/
   git clone [repository-url] minimalcode
   ```

2. Activate the theme from WordPress Admin → Appearance → Themes

3. Customize your site:
   - Go to Appearance → Customize
   - Set your social links (Twitter, GitHub, LinkedIn, Email)
   - Configure your site tagline
   - Create your navigation menus

## Theme Customization

### Adding Navigation Menus

1. Go to Appearance → Menus
2. Create two menus:
   - **Primary Menu** - Main header navigation
   - **Social Menu** - Footer links

### Setting Social Links

Go to Appearance → Customize → Social Links and enter your:
- Twitter URL
- GitHub URL
- LinkedIn URL
- Email address

### Color Scheme

The theme automatically detects your system's dark mode preference but users can manually toggle between light and dark modes using:
- The theme toggle button in the header
- Keyboard shortcut: `Ctrl/Cmd + Shift + D`

## Supported Languages for Code Highlighting

- JavaScript
- Python
- Bash/Shell
- JSON
- CSS
- PHP
- TypeScript

Additional languages can be added by modifying `functions.php`.

## Browser Support

- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)

## Development

Built with:
- Modern CSS with CSS Variables
- Vanilla JavaScript (no jQuery)
- WordPress best practices
- Mobile-first responsive design

## Credits

- Fonts: [Inter](https://rsms.me/inter/) and [JetBrains Mono](https://www.jetbrains.com/lp/mono/)
- Code Highlighting: [Prism.js](https://prismjs.com/)
- Icons: Custom SVG icons

## License

This theme is licensed under the GNU General Public License v2 or later.

## Author

Created by AutoJack for drunk.support

## Changelog

### Version 1.0.0
- Initial release
- Dark mode support
- Code syntax highlighting
- Reading progress bar
- Copy code functionality
- Fully responsive design

