# ProgressCard Component

## Overview
The ProgressCard component provides a visual representation of upload progress for individual files.

## Features
- 📊 File upload progress display
- 📋 Display file name and size
- 📈 Animated progress bar
- 🔄 Retry and cancel actions
- 🗂 File type icons

## Component States

### Pending State
- File queued for upload
- Placeholder progress indicator
- Cancel option available

### Uploading State
- Animated progress bar
- Live progress percentage
- Cancel button becomes active

### Completed State
- Success indicator
- Progress bar turned green
- Display download link

### Error State
- Error message display
- Retry option
- Error icon

### Cancelled State
- Red border
- Display cancellation message

## Props Interface

```typescript
interface ProgressCardProps {
  file: File;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error' | 'cancelled';
  onRetry: (file: File) =e void;
  onCancel: (file: File) =e void;
}
```

## Usage Examples

### Basic Usage
```tsx
cProgressCard
  file={file}
  progress={50}
  status="uploading"
  onRetry={handleRetry}
  onCancel={handleCancel}
/e
```

## Styling Classes

### Base Classes
- `.progress-card` - Main container
- `.progress-bar` - Progress bar container
- `.progress-details` - File details display
- `.progress-actions` - Action buttons

### State Classes
- `.progress-card--pending` - Pending state styling
- `.progress-card--uploading` - Uploading with animated progress
- `.progress-card--completed` - Completed state with success styling
- `.progress-card--error` - Error state styling
- `.progress-card--cancelled` - Cancelled state styling

## Accessibility

### ARIA Attributes
- `role="progressbar"` for the progress bar
- `aria-valuenow` for current progress
- `aria-valuemin` and `aria-valuemax` for progress limits
- `aria-describedby` for file details

### Keyboard Support
- Retry and cancel are keyboard accessible

## Animation & Transitions

### Uploading State
- Smooth progress bar animation
- Green transition on completion
- Red flash on error

### File Actions
- Hover effects on buttons
- Smooth transitions for retry/cancel state

## Testing Requirements

### Unit Tests
- Progress calculations
- Error handling
- Cancel/Retry actions
- State updates

### Integration Tests
- Button interactions
- Progress animations
- Error state transitions
- Accessibility compliance

## Implementation Notes

### Dependencies
- Utilizes Tailwind for styling
- Framer Motion for animations

### Design Tokens Used

### Colors
- `colors.primary[500]` - Progress bar
- `colors.success[500]` - Completed state
- `colors.error[500]` - Error state

### Typography
- `fontSize.sm` - File details
- `fontWeight.regular` - Text weight

### Spacing
- `spacing[2]` - Margin and padding
- `spacing[4]` - Button spacing

## Related Components
- DropZone - Handles file input
- TransferSummary - Summary of completed uploads
- FilePreview - Thumbnails and file types
- ErrorBoundary - Global error handling

