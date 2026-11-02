import type Mithril from 'mithril';

// The reconstructed source uses bare Mithril hyperscript (`m(...)`, `m.redraw()`)
// rather than JSX. Flarum exposes Mithril as a runtime global, so declare it here
// so the hyperscript type-checks without an import (mithril is not externalized by
// flarum-webpack-config, so importing it would bundle a second copy).
declare global {
  const m: Mithril.Static;
}

export {};
