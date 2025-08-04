# DropZone Component

## Overview
The DropZone component is the primary file upload interface for Bedrive Transfer. It provides drag-and-drop functionality with visual feedback and file validation.

## Features
- 📁 Drag and drop file handling
- 🔍 File type validation  
- 📊 Multiple file support
- 🎨 Visual feedback states
- 📏 File size limits display
- ♿ Accessibility support

## Component States

### Default State
- Empty upload area with instructions
- Upload icon and call-to-action text
- File size limit information
- Browse button for click-to-upload

### Drag Over State  
- Highlighted border and background
- Dynamic text: "Drop files here..."
- Visual emphasis with color changes
- Animated upload icon

### Has Files State
- Shows selected files list
- Modified appearance with file count
- Option to add more files
- Clear/remove functionality

### Error State
- Red border and error styling
- Error message display
- Clear error action
- Retry upload option

## Props Interface

```typescript
interface DropZoneProps {
  // Core functionality
  onFilesAccepted: (files: File[]) => void;
  onError: (error: string) => void;
  
  // Configuration
  maxFiles?: number;
  maxSize?: number; // in bytes
  acceptedFileTypes?: string[];
  disabled?: boolean;
  
  // Styling
  className?: string;
  variant?: 'default' | 'compact' | 'inline';
  
  // Content
  title?: string;
  description?: string;
  browseText?: string;
  
  // State
  isDragActive?: boolean;
  files?: File[];
  error?: string;
}
```

## Usage Examples

### Basic Usage
```tsx
<DropZone
  onFilesAccepted={handleFiles}
  onError={handleError}
  maxSize={2 * 1024 * 1024 * 1024} // 2GB
  maxFiles={10}
/>
```

### With Custom Configuration
```tsx
<DropZone
  onFilesAccepted={handleFiles}
  onError={handleError}
  acceptedFileTypes={['image/*', 'application/pdf']}
  title="Upload Images or PDFs"
  description="Drag and drop up to 5 files here"
  maxFiles={5}
  variant="compact"
/>
```

## Styling Classes

### Base Classes
- `.drop-zone` - Main container
- `.drop-zone-content` - Content area
- `.drop-zone-icon` - Upload icon
- `.drop-zone-text` - Text content
- `.drop-zone-browse` - Browse button

### State Classes
- `.drop-zone--default` - Default state
- `.drop-zone--drag-over` - Active drag state
- `.drop-zone--has-files` - Files selected state
- `.drop-zone--error` - Error state
- `.drop-zone--disabled` - Disabled state

### Responsive Classes
- `.drop-zone--mobile` - Mobile optimized
- `.drop-zone--tablet` - Tablet optimized
- `.drop-zone--desktop` - Desktop optimized

## Accessibility

### ARIA Attributes
- `role="button"` on main container
- `aria-label` with descriptive text
- `aria-describedby` for instructions
- `aria-invalid` for error state

### Keyboard Support
- Enter/Space to open file dialog
- Escape to cancel drag operation
- Tab navigation support

### Screen Reader Support
- Announces drag state changes
- Reads file count and validation info
- Error message announcements

## File Validation

### Size Validation
- Individual file size limits
- Total upload size limits
- Human-readable error messages

### Type Validation
- MIME type checking
- File extension validation
- Custom validation functions

### Security
- File content scanning hooks
- Virus scanning integration points
- Malicious file detection

## Animation & Transitions

### Drag States
- Smooth color transitions (300ms)
- Scale animations on drag over
- Border pulse effects

### File Addition
- Slide-in animations for file list
- Progress indicators
- Success/error state transitions

## Testing Requirements

### Unit Tests
- File acceptance logic
- Validation functions
- Event handling
- State management

### Integration Tests
- Drag and drop interactions
- File dialog integration
- Error handling flows
- Accessibility compliance

### Visual Tests
- Component rendering
- State transitions
- Responsive behavior
- Cross-browser compatibility

## Implementation Notes

### Dependencies
- `react-dropzone` for core functionality
- `framer-motion` for animations
- Custom validation hooks
- File type detection utilities

### Performance
- Debounced drag events
- Lazy file preview generation
- Memory management for large files
- Efficient re-renders

### Browser Support
- Modern browsers (ES6+)
- Mobile touch support
- Safari file handling quirks
- IE11 fallback (if required)

## Design Tokens Used

### Colors
- `colors.primary[500]` - Active state
- `colors.gray[300]` - Default border
- `colors.success[500]` - Success state
- `colors.error[500]` - Error state

### Spacing
- `spacing[8]` - Internal padding
- `spacing[4]` - Text spacing
- `spacing[12]` - Container height

### Typography
- `fontSize.lg` - Title text
- `fontSize.sm` - Description text
- `fontWeight.medium` - Emphasis

### Border Radius
- `borderRadius.xl` - Container radius
- `borderRadius.lg` - Button radius

## Related Components
- ProgressCard - Shows upload progress
- FilePreview - File thumbnails
- TransferOptions - Upload settings
- ErrorBoundary - Error handling
