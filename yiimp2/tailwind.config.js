/** @type {import('tailwindcss').Config} */
module.exports = {
    // Scan all PHP view templates for Tailwind class usage.
    // The CLI removes any class not found here from the compiled output.
    content: [
        './views/**/*.php',
        './components/**/*.php',
    ],
    darkMode: 'class',  // toggled by setting class="dark" on <html> (cookie-driven)
    theme: {
        extend: {},
    },
    plugins: [],
};
