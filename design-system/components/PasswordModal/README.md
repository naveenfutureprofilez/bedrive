# PasswordModal Component

## Overview
The PasswordModal component provides a dialog for users to set password protection on file transfers.

## Features
- 🔐 Password input field
- 🧩 Password strength indicator
- 🛡️ Form validation
- ❓ Security features explanation
- 🔄 Password confirmation

## Component States

### Default State
- Modal with empty input field
- Enable/disable button based on input

### Error State
- Validation error message display
- Red border on input field

## Props Interface
```typescript
interface PasswordModalProps {
  visible: boolean;
  onClose: () => void;
  onSubmit: (password: string) => void;
}
```

## Usage Examples
### Basic Usage
```tsx
.PasswordModal
  visible={true}
  onClose={handleClose}
  onSubmit={handlePasswordSubmit}
/>
```

## Styling Classes
### Base Classes
- `.password-modal` - Main container
- `.password-input` - Input field
- `.password-strength` - Strength indicator

## Accessibility
- Modal is focus-trapped
- Submits password on Enter

## Animation 6 Transitions
- Fade-in for modal
- Slide transition for input field

## Testing Requirements
### Unit Tests
- Validation logic
- Visibility toggle

### Integration Tests
- Modal interaction
- Form submissions

## Implementation Notes
### Dependencies
- Uses Tailwind for styling
- Custom logic for password validation

## Design Tokens Used
### Colors
- `colors.primary` - Buttons and icons
- `colors.error` - Error messages

### Typography
- `fontSize.md` - Input field

## Related Components
- TransferSummary - After password set
- ShareModal - Share with additional security

