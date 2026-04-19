import type { Config } from 'tailwindcss'

const config: Config = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f8f6f3',
          100: '#f0ebe6',
          200: '#e1d7cc',
          300: '#d2c3b3',
          400: '#b39580',
          500: '#94674d',
          600: '#7a5540',
          700: '#5e4333',
          800: '#453226',
          900: '#2c201a',
        },
        accent: {
          50: '#fff9e6',
          100: '#fff0cc',
          200: '#ffe199',
          300: '#ffd966',
          400: '#ffcc33',
          500: '#ffb800',
          600: '#cc9200',
          700: '#996b00',
          800: '#664600',
          900: '#331f00',
        },
        gray: {
          50: '#f9f9f9',
          100: '#f3f3f3',
          200: '#e8e8e8',
          300: '#d0d0d0',
          400: '#b0b0b0',
          500: '#808080',
          600: '#606060',
          700: '#404040',
          800: '#202020',
          900: '#0a0a0a',
        },
      },
      borderWidth: {
        3: '3px',
        4: '4px',
        6: '6px',
        8: '8px',
      },
      boxShadow: {
        'hard-sm': '4px 4px 0px rgba(0, 0, 0, 0.1)',
        'hard': '8px 8px 0px rgba(0, 0, 0, 0.15)',
        'hard-lg': '12px 12px 0px rgba(0, 0, 0, 0.2)',
        'hard-xl': '16px 16px 0px rgba(0, 0, 0, 0.25)',
      },
      transform: {
        'translate-hard': 'translate(8px, 8px)',
        'translate-hard-sm': 'translate(4px, 4px)',
        'translate-hard-lg': 'translate(12px, 12px)',
      },
      transition: {
        'hard': 'all 0.15s cubic-bezier(0.34, 1.56, 0.64, 1)',
      },
      fontFamily: {
        sans: [
          '-apple-system',
          'BlinkMacSystemFont',
          '"Segoe UI"',
          'Roboto',
          '"Helvetica Neue"',
          'Arial',
          '"Noto Sans"',
          'sans-serif',
        ],
        mono: [
          'Menlo',
          'Monaco',
          '"Courier New"',
          'monospace',
        ],
      },
      fontSize: {
        xs: ['0.75rem', { lineHeight: '1rem' }],
        sm: ['0.875rem', { lineHeight: '1.25rem' }],
        base: ['1rem', { lineHeight: '1.5rem' }],
        lg: ['1.125rem', { lineHeight: '1.75rem' }],
        xl: ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1' }],
      },
    },
  },
  plugins: [],
}

export default config
