export type TreeValue = string | number;

export type TreeItemLike = {
  value: TreeValue;
  parentUid: TreeValue | null;
};

export function updateTreeSelection(
  activeItems: TreeValue[],
  value: TreeValue,
  selected: boolean,
): TreeValue[] {
  return selected
    ? [...new Set([...activeItems, value])]
    : activeItems.filter((item) => item !== value);
}

export function getDescendantValues(
  items: TreeItemLike[],
  parentValue: TreeValue,
): TreeValue[] {
  const descendants: TreeValue[] = [];
  const pending = [parentValue];

  while (pending.length > 0) {
    const currentValue = pending.pop();

    if (currentValue === undefined) {
      continue;
    }

    for (const item of items) {
      if (item.parentUid !== currentValue) {
        continue;
      }

      descendants.push(item.value);
      pending.push(item.value);
    }
  }

  return descendants;
}

export function updateDescendantSelection(
  activeItems: TreeValue[],
  items: TreeItemLike[],
  parentValue: TreeValue,
  selected: boolean,
): TreeValue[] {
  const descendantValues = getDescendantValues(items, parentValue);

  return selected
    ? [...new Set([...activeItems, ...descendantValues])]
    : activeItems.filter((item) => !descendantValues.includes(item));
}

export function updateTreeExpansion(
  expandedItems: Record<TreeValue, boolean>,
  value: TreeValue,
  expanded: boolean,
): Record<TreeValue, boolean> {
  return {
    ...expandedItems,
    [value]: expanded,
  };
}
