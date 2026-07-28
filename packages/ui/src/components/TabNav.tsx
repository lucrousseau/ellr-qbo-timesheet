/**
 * @file Horizontal tab navigation for sectioned dashboards.
 */

import { useCallback, type KeyboardEvent } from 'react'

type TabNavItem<T extends string> = {
  id: T
  label: string
}

type TabNavProps<T extends string> = {
  items: TabNavItem<T>[]
  activeId: T
  onChange: (id: T) => void
  ariaLabel: string
  idPrefix?: string
}

/**
 * Builds a stable DOM id for a tab button.
 * @param prefix Namespace for generated ids.
 * @param tabId Tab item id.
 * @returns Element id for the tab button.
 */
export function tabButtonId(prefix: string, tabId: string): string {
  return `${prefix}-tab-${tabId}`
}

/**
 * Builds a stable DOM id for a tab panel.
 * @param prefix Namespace for generated ids.
 * @param tabId Tab item id.
 * @returns Element id for the tab panel.
 */
export function tabPanelId(prefix: string, tabId: string): string {
  return `${prefix}-panel-${tabId}`
}

/**
 * Accessible tab list for switching between dashboard sections.
 * @param props.items Tab definitions with stable ids and labels.
 * @param props.activeId Currently selected tab id.
 * @param props.onChange Invoked when a tab is selected.
 * @param props.ariaLabel Accessible name for the tab list.
 * @param props.idPrefix Optional prefix for tab and panel element ids.
 * @returns Tab navigation bar.
 */
export function TabNav<T extends string>({
  items,
  activeId,
  onChange,
  ariaLabel,
  idPrefix = 'ellr',
}: TabNavProps<T>) {
  const focusTab = useCallback(
    (index: number) => {
      const tab = items[index]
      if (tab) {
        onChange(tab.id)
      }
    },
    [items, onChange],
  )

  const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
    if (event.key === 'ArrowRight') {
      event.preventDefault()
      focusTab((index + 1) % items.length)
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault()
      focusTab((index - 1 + items.length) % items.length)
    }

    if (event.key === 'Home') {
      event.preventDefault()
      focusTab(0)
    }

    if (event.key === 'End') {
      event.preventDefault()
      focusTab(items.length - 1)
    }
  }

  return (
    <nav aria-label={ariaLabel}>
      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {items.map((item, index) => {
          const isActive = item.id === activeId
          const buttonId = tabButtonId(idPrefix, item.id)

          return (
            <button
              key={item.id}
              id={buttonId}
              type="button"
              role="tab"
              aria-selected={isActive}
              aria-controls={tabPanelId(idPrefix, item.id)}
              tabIndex={isActive ? 0 : -1}
              className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                isActive
                  ? 'border-slate-900 text-slate-900'
                  : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
              }`}
              onClick={() => onChange(item.id)}
              onKeyDown={(event) => handleKeyDown(event, index)}
            >
              {item.label}
            </button>
          )
        })}
      </div>
    </nav>
  )
}

export type { TabNavItem }
