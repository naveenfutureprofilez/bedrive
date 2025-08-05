import React from 'react';
import { motion } from 'framer-motion';
import clsx from 'clsx';

export type ProgressBarVariant = 'default' | 'success' | 'error' | 'warning';
export type ProgressBarSize = 'sm' | 'md' | 'lg';

interface ProgressBarProps {
  className?: string;
  progress: number; // Value between 0 and 100
  variant?: ProgressBarVariant;
  size?: ProgressBarSize;
  showPercentage?: boolean;
  showAnimation?: boolean;
  label?: string;
  indeterminate?: boolean;
}

const variantStyles = {
  default: {
    bg: 'bg-primary',
    text: 'text-primary-foreground',
  },
  success: {
    bg: 'bg-positive',
    text: 'text-positive-foreground',
  },
  error: {
    bg: 'bg-danger',
    text: 'text-danger-foreground',
  },
  warning: {
    bg: 'bg-warning',
    text: 'text-warning-foreground',
  },
};

const sizeStyles = {
  sm: {
    height: 'h-1.5',
    textSize: 'text-xs',
    padding: 'px-1',
  },
  md: {
    height: 'h-2.5',
    textSize: 'text-xs',
    padding: 'px-2 py-0.5',
  },
  lg: {
    height: 'h-4',
    textSize: 'text-sm',
    padding: 'px-3 py-1',
  },
};

export function ProgressBar({
  className,
  progress,
  variant = 'default',
  size = 'md',
  showPercentage = false,
  showAnimation = true,
  label,
  indeterminate = false,
}: ProgressBarProps) {
  const clampedProgress = Math.min(Math.max(progress, 0), 100);
  const variantClasses = variantStyles[variant];
  const sizeClasses = sizeStyles[size];

  return (
    <div className={clsx('w-full', className)}>
      {label && (
        <div className="flex justify-between items-center mb-2">
          <span className="text-sm font-medium text-gray-700">{label}</span>
          {showPercentage && !indeterminate && (
            <span className="text-sm text-gray-500">{Math.round(clampedProgress)}%</span>
          )}
        </div>
      )}
      
      <div className={clsx(
        'w-full bg-gray-200 rounded-full overflow-hidden',
        sizeClasses.height
      )}>
        {indeterminate ? (
          <motion.div
            className={clsx(
              'h-full rounded-full',
              variantClasses.bg,
              'w-1/3'
            )}
            animate={{
              x: ['-100%', '300%'],
            }}
            transition={{
              duration: 1.5,
              repeat: Infinity,
              ease: 'easeInOut',
            }}
          />
        ) : (
          <motion.div
            className={clsx(
              'h-full rounded-full flex items-center justify-center font-medium',
              variantClasses.bg,
              variantClasses.text,
              sizeClasses.textSize,
              sizeClasses.padding,
              {
                'min-w-0': !showPercentage,
              }
            )}
            initial={{ width: showAnimation ? 0 : `${clampedProgress}%` }}
            animate={{ width: `${clampedProgress}%` }}
            transition={{
              duration: showAnimation ? 0.5 : 0,
              ease: 'easeOut',
            }}
          >
            {showPercentage && size !== 'sm' && (
              <span className="whitespace-nowrap">
                {Math.round(clampedProgress)}%
              </span>
            )}
          </motion.div>
        )}
      </div>
    </div>
  );
}

