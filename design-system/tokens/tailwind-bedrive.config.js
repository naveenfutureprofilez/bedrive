const { colors, typography, spacing, borderRadius, boxShadow } = require('./design-tokens');
const {
  sharedOverride,
  sharedExtend,
  sharedPlugins,
} = require('../../common/foundation/resources/client/shared.tailwind');

/**
 * Bedrive-specific Tailwind configuration
 * Extends the existing shared configuration with design system tokens
 */
module.exports = {
  content: [
    './resources/client/**/*.{ts,tsx}',
    './common/foundation/resources/client/**/*.{ts,tsx}',
    './design-system/components/**/*.{ts,tsx}',
    './common/foundation/resources/views/install/**/*.blade.php',
    './common/foundation/resources/views/framework.blade.php',
  ],
  
  darkMode: 'class',
  
  theme: {
    // Inherit shared overrides
    ...sharedOverride,
    
    extend: {
      // Inherit shared extensions
      ...sharedExtend,
      
      // Add design system tokens
      colors: {
        // Keep existing Bedrive color system
        ...sharedOverride.colors({}),
        
        // Add new design system colors
        ...colors,
        
        // Transfer-specific brand colors
        'transfer-primary': colors.primary[500],
        'transfer-primary-light': colors.primary[100],
        'transfer-primary-dark': colors.primary[700],
        'transfer-success': colors.success[500],
        'transfer-warning': colors.warning[500],
        'transfer-error': colors.error[500],
      },
      
      fontFamily: {
        ...typography.fontFamily,
      },
      
      fontSize: {
        ...typography.fontSize,
      },
      
      fontWeight: {
        ...typography.fontWeight,
      },
      
      spacing: {
        ...spacing,
      },
      
      borderRadius: {
        ...borderRadius,
        // Keep existing Bedrive radius variables
        button: 'var(--be-button-radius, 0.25rem)',
        input: 'var(--be-input-radius, 0.25rem)',
        panel: 'var(--be-panel-radius, 0.25rem)',
      },
      
      boxShadow: {
        ...boxShadow,
      },
      
      // Component-specific extensions
      dropShadow: {
        'upload': '0 4px 20px rgb(0 0 0 / 0.1)',
        'card': '0 2px 10px rgb(0 0 0 / 0.05)',
      },
      
      // Animation extensions for transfer components
      animation: {
        'fade-in': 'fadeIn 0.3s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'pulse-subtle': 'pulseSubtle 2s infinite',
        'upload-progress': 'uploadProgress 0.3s ease-out',
      },
      
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        pulseSubtle: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.8' },
        },
        uploadProgress: {
          '0%': { width: '0%' },
          '100%': { width: 'var(--progress-width)' },
        },
      },
      
      // Transfer component specific sizing
      minHeight: {
        'upload-area': '200px',
        'drop-zone': '300px',
      },
      
      maxWidth: {
        'upload-card': '800px',
        'transfer-modal': '600px',
      },
    },
  },
  
  variants: {
    extend: {
      // Enable variants for upload states
      backgroundColor: ['active', 'disabled', 'drag-over'],
      borderColor: ['active', 'disabled', 'drag-over'],
      textColor: ['active', 'disabled'],
      opacity: ['disabled'],
    },
  },
  
  plugins: [
    require('@tailwindcss/typography'),
    ...sharedPlugins(require('tailwindcss/plugin')),
    
    // Custom plugin for transfer-specific utilities
    require('tailwindcss/plugin')(function({ addUtilities, addComponents, theme }) {
      addUtilities({
        // Upload states
        '.upload-dragging': {
          '@apply border-transfer-primary bg-transfer-primary-light': {},
        },
        '.upload-completed': {
          '@apply border-transfer-success bg-green-50': {},
        },
        '.upload-error': {
          '@apply border-transfer-error bg-red-50': {},
        },
        
        // Progress bar utilities
        '.progress-bar': {
          '@apply relative overflow-hidden bg-gray-200 rounded-full': {},
        },
        '.progress-fill': {
          '@apply h-full bg-gradient-to-r from-transfer-primary to-blue-600 transition-all duration-300 ease-out rounded-full': {},
        },
        
        // File item states
        '.file-item-pending': {
          '@apply border-gray-200 bg-white': {},
        },
        '.file-item-uploading': {
          '@apply border-blue-200 bg-blue-50': {},
        },
        '.file-item-completed': {
          '@apply border-green-200 bg-green-50': {},
        },
        '.file-item-error': {
          '@apply border-red-200 bg-red-50': {},
        },
      });
      
      addComponents({
        // Drop zone component
        '.drop-zone': {
          '@apply border-2 border-dashed border-gray-300 rounded-2xl p-8 text-center transition-all cursor-pointer hover:border-transfer-primary hover:bg-transfer-primary-light/50': {},
        },
        
        // Upload card component
        '.upload-card': {
          '@apply bg-white rounded-2xl shadow-lg p-6 border border-gray-100': {},
        },
        
        // Progress card component
        '.progress-card': {
          '@apply bg-white rounded-xl shadow-md p-4 border border-gray-100': {},
        },
        
        // Transfer summary component
        '.transfer-summary': {
          '@apply bg-gradient-to-r from-green-50 to-blue-50 rounded-xl p-6 border border-green-200': {},
        },
        
        // Modal components
        '.modal-overlay': {
          '@apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50': {},
        },
        '.modal-content': {
          '@apply bg-white rounded-2xl shadow-2xl max-w-md w-full p-6': {},
        },
        
        // Expired screen component
        '.expired-screen': {
          '@apply text-center py-12 px-6': {},
        },
      });
    }),
  ],
};
