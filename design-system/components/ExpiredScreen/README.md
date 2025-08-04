# ExpiredScreen Component

## Overview
The ExpiredScreen component displays when a transfer link has expired, providing user-friendly messaging and alternative actions.

## Features
- 📅 Expiration message display
- 🎨 Friendly error illustration
- 🔄 Alternative action buttons
- 📞 Contact information
- 💡 New transfer suggestion

## Component States

### Default State
- Clear expiration message
- Action buttons for alternatives

### With Contact State
- Additional contact information
- Support link or email

## Props Interface
```typescript
interface ExpiredScreenProps {
  transferId?: string;
  expiredDate: string;
  onNewTransfer: () => void;
  showContact?: boolean;
}
```

## Usage Examples
### Basic Usage
```tsx
<ExpiredScreen
  expiredDate="2024-01-15"
  onNewTransfer={handleNewTransfer}
  showContact={true}
/>
```

## Styling Classes
### Base Classes
- `.expired-screen` - Main container
- `.expired-message` - Primary message
- `.expired-actions` - Action buttons

## Accessibility
- Clear messaging for screen readers
- Keyboard navigation for actions

## Animation & Transitions
- Fade-in for the entire screen
- Gentle bounce for illustration

## Testing Requirements
### Unit Tests
- Date formatting
- Action callbacks

### Integration Tests
- Screen display logic
- Navigation to new transfer

## Implementation Notes
### Dependencies
- React for UI components
- Date formatting utilities

## Design Tokens Used
### Colors
- `colors.gray` - Text and borders
- `colors.primary` - Action buttons

### Typography
- `fontSize.lg` - Heading
- `fontSize.base` - Body text

### Spacing
- `spacing.8` - Section spacing

## Related Components
- TransferSummary - Success counterpart
- DropZone - New transfer initiation
