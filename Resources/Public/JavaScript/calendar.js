// node_modules/svelte/src/runtime/internal/utils.js
function noop() {
}
var identity = (x) => x;
function assign(tar, src) {
  for (const k in src) tar[k] = src[k];
  return (
    /** @type {T & S} */
    tar
  );
}
function run(fn) {
  return fn();
}
function blank_object() {
  return /* @__PURE__ */ Object.create(null);
}
function run_all(fns) {
  fns.forEach(run);
}
function is_function(thing) {
  return typeof thing === "function";
}
function safe_not_equal(a, b) {
  return a != a ? b == b : a !== b || a && typeof a === "object" || typeof a === "function";
}
function is_empty(obj) {
  return Object.keys(obj).length === 0;
}
function subscribe(store, ...callbacks) {
  if (store == null) {
    for (const callback of callbacks) {
      callback(void 0);
    }
    return noop;
  }
  const unsub = store.subscribe(...callbacks);
  return unsub.unsubscribe ? () => unsub.unsubscribe() : unsub;
}
function get_store_value(store) {
  let value;
  subscribe(store, (_) => value = _)();
  return value;
}
function component_subscribe(component, store, callback) {
  component.$$.on_destroy.push(subscribe(store, callback));
}
function create_slot(definition, ctx, $$scope, fn) {
  if (definition) {
    const slot_ctx = get_slot_context(definition, ctx, $$scope, fn);
    return definition[0](slot_ctx);
  }
}
function get_slot_context(definition, ctx, $$scope, fn) {
  return definition[1] && fn ? assign($$scope.ctx.slice(), definition[1](fn(ctx))) : $$scope.ctx;
}
function get_slot_changes(definition, $$scope, dirty, fn) {
  if (definition[2] && fn) {
    const lets = definition[2](fn(dirty));
    if ($$scope.dirty === void 0) {
      return lets;
    }
    if (typeof lets === "object") {
      const merged = [];
      const len = Math.max($$scope.dirty.length, lets.length);
      for (let i = 0; i < len; i += 1) {
        merged[i] = $$scope.dirty[i] | lets[i];
      }
      return merged;
    }
    return $$scope.dirty | lets;
  }
  return $$scope.dirty;
}
function update_slot_base(slot, slot_definition, ctx, $$scope, slot_changes, get_slot_context_fn) {
  if (slot_changes) {
    const slot_context = get_slot_context(slot_definition, ctx, $$scope, get_slot_context_fn);
    slot.p(slot_context, slot_changes);
  }
}
function get_all_dirty_from_scope($$scope) {
  if ($$scope.ctx.length > 32) {
    const dirty = [];
    const length = $$scope.ctx.length / 32;
    for (let i = 0; i < length; i++) {
      dirty[i] = -1;
    }
    return dirty;
  }
  return -1;
}
function set_store_value(store, ret, value) {
  store.set(value);
  return ret;
}
function action_destroyer(action_result) {
  return action_result && is_function(action_result.destroy) ? action_result.destroy : noop;
}

// node_modules/svelte/src/runtime/internal/globals.js
var globals = typeof window !== "undefined" ? window : typeof globalThis !== "undefined" ? globalThis : (
  // @ts-ignore Node typings have this
  global
);

// node_modules/svelte/src/runtime/internal/ResizeObserverSingleton.js
var ResizeObserverSingleton = class _ResizeObserverSingleton {
  /**
   * @private
   * @readonly
   * @type {WeakMap<Element, import('./private.js').Listener>}
   */
  _listeners = "WeakMap" in globals ? /* @__PURE__ */ new WeakMap() : void 0;
  /**
   * @private
   * @type {ResizeObserver}
   */
  _observer = void 0;
  /** @type {ResizeObserverOptions} */
  options;
  /** @param {ResizeObserverOptions} options */
  constructor(options) {
    this.options = options;
  }
  /**
   * @param {Element} element
   * @param {import('./private.js').Listener} listener
   * @returns {() => void}
   */
  observe(element2, listener) {
    this._listeners.set(element2, listener);
    this._getObserver().observe(element2, this.options);
    return () => {
      this._listeners.delete(element2);
      this._observer.unobserve(element2);
    };
  }
  /**
   * @private
   */
  _getObserver() {
    return this._observer ?? (this._observer = new ResizeObserver((entries) => {
      for (const entry of entries) {
        _ResizeObserverSingleton.entries.set(entry.target, entry);
        this._listeners.get(entry.target)?.(entry);
      }
    }));
  }
};
ResizeObserverSingleton.entries = "WeakMap" in globals ? /* @__PURE__ */ new WeakMap() : void 0;

// node_modules/svelte/src/runtime/internal/dom.js
var is_hydrating = false;
function start_hydrating() {
  is_hydrating = true;
}
function end_hydrating() {
  is_hydrating = false;
}
function append(target, node) {
  target.appendChild(node);
}
function insert(target, node, anchor) {
  target.insertBefore(node, anchor || null);
}
function detach(node) {
  if (node.parentNode) {
    node.parentNode.removeChild(node);
  }
}
function destroy_each(iterations, detaching) {
  for (let i = 0; i < iterations.length; i += 1) {
    if (iterations[i]) iterations[i].d(detaching);
  }
}
function element(name) {
  return document.createElement(name);
}
function text(data) {
  return document.createTextNode(data);
}
function space() {
  return text(" ");
}
function empty() {
  return text("");
}
function listen(node, event, handler, options) {
  node.addEventListener(event, handler, options);
  return () => node.removeEventListener(event, handler, options);
}
function stop_propagation(fn) {
  return function(event) {
    event.stopPropagation();
    return fn.call(this, event);
  };
}
function attr(node, attribute, value) {
  if (value == null) node.removeAttribute(attribute);
  else if (node.getAttribute(attribute) !== value) node.setAttribute(attribute, value);
}
function children(element2) {
  return Array.from(element2.childNodes);
}
function set_data(text2, data) {
  data = "" + data;
  if (text2.data === data) return;
  text2.data = /** @type {string} */
  data;
}
function set_style(node, key, value, important) {
  if (value == null) {
    node.style.removeProperty(key);
  } else {
    node.style.setProperty(key, value, important ? "important" : "");
  }
}
function get_custom_elements_slots(element2) {
  const result = {};
  element2.childNodes.forEach(
    /** @param {Element} node */
    (node) => {
      result[node.slot || "default"] = true;
    }
  );
  return result;
}
function construct_svelte_component(component, props) {
  return new component(props);
}

// node_modules/svelte/src/runtime/internal/lifecycle.js
var current_component;
function set_current_component(component) {
  current_component = component;
}
function get_current_component() {
  if (!current_component) throw new Error("Function called outside component initialization");
  return current_component;
}
function beforeUpdate(fn) {
  get_current_component().$$.before_update.push(fn);
}
function onMount(fn) {
  get_current_component().$$.on_mount.push(fn);
}
function afterUpdate(fn) {
  get_current_component().$$.after_update.push(fn);
}
function setContext(key, context) {
  get_current_component().$$.context.set(key, context);
  return context;
}
function getContext(key) {
  return get_current_component().$$.context.get(key);
}
function bubble(component, event) {
  const callbacks = component.$$.callbacks[event.type];
  if (callbacks) {
    callbacks.slice().forEach((fn) => fn.call(this, event));
  }
}

// node_modules/svelte/src/runtime/internal/scheduler.js
var dirty_components = [];
var binding_callbacks = [];
var render_callbacks = [];
var flush_callbacks = [];
var resolved_promise = /* @__PURE__ */ Promise.resolve();
var update_scheduled = false;
function schedule_update() {
  if (!update_scheduled) {
    update_scheduled = true;
    resolved_promise.then(flush);
  }
}
function tick() {
  schedule_update();
  return resolved_promise;
}
function add_render_callback(fn) {
  render_callbacks.push(fn);
}
var seen_callbacks = /* @__PURE__ */ new Set();
var flushidx = 0;
function flush() {
  if (flushidx !== 0) {
    return;
  }
  const saved_component = current_component;
  do {
    try {
      while (flushidx < dirty_components.length) {
        const component = dirty_components[flushidx];
        flushidx++;
        set_current_component(component);
        update(component.$$);
      }
    } catch (e) {
      dirty_components.length = 0;
      flushidx = 0;
      throw e;
    }
    set_current_component(null);
    dirty_components.length = 0;
    flushidx = 0;
    while (binding_callbacks.length) binding_callbacks.pop()();
    for (let i = 0; i < render_callbacks.length; i += 1) {
      const callback = render_callbacks[i];
      if (!seen_callbacks.has(callback)) {
        seen_callbacks.add(callback);
        callback();
      }
    }
    render_callbacks.length = 0;
  } while (dirty_components.length);
  while (flush_callbacks.length) {
    flush_callbacks.pop()();
  }
  update_scheduled = false;
  seen_callbacks.clear();
  set_current_component(saved_component);
}
function update($$) {
  if ($$.fragment !== null) {
    $$.update();
    run_all($$.before_update);
    const dirty = $$.dirty;
    $$.dirty = [-1];
    $$.fragment && $$.fragment.p($$.ctx, dirty);
    $$.after_update.forEach(add_render_callback);
  }
}
function flush_render_callbacks(fns) {
  const filtered = [];
  const targets = [];
  render_callbacks.forEach((c) => fns.indexOf(c) === -1 ? filtered.push(c) : targets.push(c));
  targets.forEach((c) => c());
  render_callbacks = filtered;
}

// node_modules/svelte/src/runtime/internal/transitions.js
var outroing = /* @__PURE__ */ new Set();
var outros;
function group_outros() {
  outros = {
    r: 0,
    c: [],
    p: outros
    // parent group
  };
}
function check_outros() {
  if (!outros.r) {
    run_all(outros.c);
  }
  outros = outros.p;
}
function transition_in(block, local) {
  if (block && block.i) {
    outroing.delete(block);
    block.i(local);
  }
}
function transition_out(block, local, detach2, callback) {
  if (block && block.o) {
    if (outroing.has(block)) return;
    outroing.add(block);
    outros.c.push(() => {
      outroing.delete(block);
      if (callback) {
        if (detach2) block.d(1);
        callback();
      }
    });
    block.o(local);
  } else if (callback) {
    callback();
  }
}

// node_modules/svelte/src/runtime/internal/each.js
function ensure_array_like(array_like_or_iterator) {
  return array_like_or_iterator?.length !== void 0 ? array_like_or_iterator : Array.from(array_like_or_iterator);
}
function outro_and_destroy_block(block, lookup) {
  transition_out(block, 1, 1, () => {
    lookup.delete(block.key);
  });
}
function update_keyed_each(old_blocks, dirty, get_key, dynamic, ctx, list, lookup, node, destroy, create_each_block5, next, get_context) {
  let o = old_blocks.length;
  let n = list.length;
  let i = o;
  const old_indexes = {};
  while (i--) old_indexes[old_blocks[i].key] = i;
  const new_blocks = [];
  const new_lookup = /* @__PURE__ */ new Map();
  const deltas = /* @__PURE__ */ new Map();
  const updates = [];
  i = n;
  while (i--) {
    const child_ctx = get_context(ctx, list, i);
    const key = get_key(child_ctx);
    let block = lookup.get(key);
    if (!block) {
      block = create_each_block5(key, child_ctx);
      block.c();
    } else if (dynamic) {
      updates.push(() => block.p(child_ctx, dirty));
    }
    new_lookup.set(key, new_blocks[i] = block);
    if (key in old_indexes) deltas.set(key, Math.abs(i - old_indexes[key]));
  }
  const will_move = /* @__PURE__ */ new Set();
  const did_move = /* @__PURE__ */ new Set();
  function insert2(block) {
    transition_in(block, 1);
    block.m(node, next);
    lookup.set(block.key, block);
    next = block.first;
    n--;
  }
  while (o && n) {
    const new_block = new_blocks[n - 1];
    const old_block = old_blocks[o - 1];
    const new_key = new_block.key;
    const old_key = old_block.key;
    if (new_block === old_block) {
      next = new_block.first;
      o--;
      n--;
    } else if (!new_lookup.has(old_key)) {
      destroy(old_block, lookup);
      o--;
    } else if (!lookup.has(new_key) || will_move.has(new_key)) {
      insert2(new_block);
    } else if (did_move.has(old_key)) {
      o--;
    } else if (deltas.get(new_key) > deltas.get(old_key)) {
      did_move.add(new_key);
      insert2(new_block);
    } else {
      will_move.add(old_key);
      o--;
    }
  }
  while (o--) {
    const old_block = old_blocks[o];
    if (!new_lookup.has(old_block.key)) destroy(old_block, lookup);
  }
  while (n) insert2(new_blocks[n - 1]);
  run_all(updates);
  return new_blocks;
}

// node_modules/svelte/src/shared/boolean_attributes.js
var _boolean_attributes = (
  /** @type {const} */
  [
    "allowfullscreen",
    "allowpaymentrequest",
    "async",
    "autofocus",
    "autoplay",
    "checked",
    "controls",
    "default",
    "defer",
    "disabled",
    "formnovalidate",
    "hidden",
    "inert",
    "ismap",
    "loop",
    "multiple",
    "muted",
    "nomodule",
    "novalidate",
    "open",
    "playsinline",
    "readonly",
    "required",
    "reversed",
    "selected"
  ]
);
var boolean_attributes = /* @__PURE__ */ new Set([..._boolean_attributes]);

// node_modules/svelte/src/runtime/internal/Component.js
function create_component(block) {
  block && block.c();
}
function mount_component(component, target, anchor) {
  const { fragment, after_update } = component.$$;
  fragment && fragment.m(target, anchor);
  add_render_callback(() => {
    const new_on_destroy = component.$$.on_mount.map(run).filter(is_function);
    if (component.$$.on_destroy) {
      component.$$.on_destroy.push(...new_on_destroy);
    } else {
      run_all(new_on_destroy);
    }
    component.$$.on_mount = [];
  });
  after_update.forEach(add_render_callback);
}
function destroy_component(component, detaching) {
  const $$ = component.$$;
  if ($$.fragment !== null) {
    flush_render_callbacks($$.after_update);
    run_all($$.on_destroy);
    $$.fragment && $$.fragment.d(detaching);
    $$.on_destroy = $$.fragment = null;
    $$.ctx = [];
  }
}
function make_dirty(component, i) {
  if (component.$$.dirty[0] === -1) {
    dirty_components.push(component);
    schedule_update();
    component.$$.dirty.fill(0);
  }
  component.$$.dirty[i / 31 | 0] |= 1 << i % 31;
}
function init(component, options, instance5, create_fragment5, not_equal, props, append_styles = null, dirty = [-1]) {
  const parent_component = current_component;
  set_current_component(component);
  const $$ = component.$$ = {
    fragment: null,
    ctx: [],
    // state
    props,
    update: noop,
    not_equal,
    bound: blank_object(),
    // lifecycle
    on_mount: [],
    on_destroy: [],
    on_disconnect: [],
    before_update: [],
    after_update: [],
    context: new Map(options.context || (parent_component ? parent_component.$$.context : [])),
    // everything else
    callbacks: blank_object(),
    dirty,
    skip_bound: false,
    root: options.target || parent_component.$$.root
  };
  append_styles && append_styles($$.root);
  let ready = false;
  $$.ctx = instance5 ? instance5(component, options.props || {}, (i, ret, ...rest) => {
    const value = rest.length ? rest[0] : ret;
    if ($$.ctx && not_equal($$.ctx[i], $$.ctx[i] = value)) {
      if (!$$.skip_bound && $$.bound[i]) $$.bound[i](value);
      if (ready) make_dirty(component, i);
    }
    return ret;
  }) : [];
  $$.update();
  ready = true;
  run_all($$.before_update);
  $$.fragment = create_fragment5 ? create_fragment5($$.ctx) : false;
  if (options.target) {
    if (options.hydrate) {
      start_hydrating();
      const nodes = children(options.target);
      $$.fragment && $$.fragment.l(nodes);
      nodes.forEach(detach);
    } else {
      $$.fragment && $$.fragment.c();
    }
    if (options.intro) transition_in(component.$$.fragment);
    mount_component(component, options.target, options.anchor);
    end_hydrating();
    flush();
  }
  set_current_component(parent_component);
}
var SvelteElement;
if (typeof HTMLElement === "function") {
  SvelteElement = class extends HTMLElement {
    /** The Svelte component constructor */
    $$ctor;
    /** Slots */
    $$s;
    /** The Svelte component instance */
    $$c;
    /** Whether or not the custom element is connected */
    $$cn = false;
    /** Component props data */
    $$d = {};
    /** `true` if currently in the process of reflecting component props back to attributes */
    $$r = false;
    /** @type {Record<string, CustomElementPropDefinition>} Props definition (name, reflected, type etc) */
    $$p_d = {};
    /** @type {Record<string, Function[]>} Event listeners */
    $$l = {};
    /** @type {Map<Function, Function>} Event listener unsubscribe functions */
    $$l_u = /* @__PURE__ */ new Map();
    constructor($$componentCtor, $$slots, use_shadow_dom) {
      super();
      this.$$ctor = $$componentCtor;
      this.$$s = $$slots;
      if (use_shadow_dom) {
        this.attachShadow({ mode: "open" });
      }
    }
    addEventListener(type, listener, options) {
      this.$$l[type] = this.$$l[type] || [];
      this.$$l[type].push(listener);
      if (this.$$c) {
        const unsub = this.$$c.$on(type, listener);
        this.$$l_u.set(listener, unsub);
      }
      super.addEventListener(type, listener, options);
    }
    removeEventListener(type, listener, options) {
      super.removeEventListener(type, listener, options);
      if (this.$$c) {
        const unsub = this.$$l_u.get(listener);
        if (unsub) {
          unsub();
          this.$$l_u.delete(listener);
        }
      }
      if (this.$$l[type]) {
        const idx = this.$$l[type].indexOf(listener);
        if (idx >= 0) {
          this.$$l[type].splice(idx, 1);
        }
      }
    }
    async connectedCallback() {
      this.$$cn = true;
      if (!this.$$c) {
        let create_slot2 = function(name) {
          return () => {
            let node;
            const obj = {
              c: function create() {
                node = element("slot");
                if (name !== "default") {
                  attr(node, "name", name);
                }
              },
              /**
               * @param {HTMLElement} target
               * @param {HTMLElement} [anchor]
               */
              m: function mount(target, anchor) {
                insert(target, node, anchor);
              },
              d: function destroy(detaching) {
                if (detaching) {
                  detach(node);
                }
              }
            };
            return obj;
          };
        };
        await Promise.resolve();
        if (!this.$$cn || this.$$c) {
          return;
        }
        const $$slots = {};
        const existing_slots = get_custom_elements_slots(this);
        for (const name of this.$$s) {
          if (name in existing_slots) {
            $$slots[name] = [create_slot2(name)];
          }
        }
        for (const attribute of this.attributes) {
          const name = this.$$g_p(attribute.name);
          if (!(name in this.$$d)) {
            this.$$d[name] = get_custom_element_value(name, attribute.value, this.$$p_d, "toProp");
          }
        }
        for (const key in this.$$p_d) {
          if (!(key in this.$$d) && this[key] !== void 0) {
            this.$$d[key] = this[key];
            delete this[key];
          }
        }
        this.$$c = new this.$$ctor({
          target: this.shadowRoot || this,
          props: {
            ...this.$$d,
            $$slots,
            $$scope: {
              ctx: []
            }
          }
        });
        const reflect_attributes = () => {
          this.$$r = true;
          for (const key in this.$$p_d) {
            this.$$d[key] = this.$$c.$$.ctx[this.$$c.$$.props[key]];
            if (this.$$p_d[key].reflect) {
              const attribute_value = get_custom_element_value(
                key,
                this.$$d[key],
                this.$$p_d,
                "toAttribute"
              );
              if (attribute_value == null) {
                this.removeAttribute(this.$$p_d[key].attribute || key);
              } else {
                this.setAttribute(this.$$p_d[key].attribute || key, attribute_value);
              }
            }
          }
          this.$$r = false;
        };
        this.$$c.$$.after_update.push(reflect_attributes);
        reflect_attributes();
        for (const type in this.$$l) {
          for (const listener of this.$$l[type]) {
            const unsub = this.$$c.$on(type, listener);
            this.$$l_u.set(listener, unsub);
          }
        }
        this.$$l = {};
      }
    }
    // We don't need this when working within Svelte code, but for compatibility of people using this outside of Svelte
    // and setting attributes through setAttribute etc, this is helpful
    attributeChangedCallback(attr2, _oldValue, newValue) {
      if (this.$$r) return;
      attr2 = this.$$g_p(attr2);
      this.$$d[attr2] = get_custom_element_value(attr2, newValue, this.$$p_d, "toProp");
      this.$$c?.$set({ [attr2]: this.$$d[attr2] });
    }
    disconnectedCallback() {
      this.$$cn = false;
      Promise.resolve().then(() => {
        if (!this.$$cn && this.$$c) {
          this.$$c.$destroy();
          this.$$c = void 0;
        }
      });
    }
    $$g_p(attribute_name) {
      return Object.keys(this.$$p_d).find(
        (key) => this.$$p_d[key].attribute === attribute_name || !this.$$p_d[key].attribute && key.toLowerCase() === attribute_name
      ) || attribute_name;
    }
  };
}
function get_custom_element_value(prop, value, props_definition, transform) {
  const type = props_definition[prop]?.type;
  value = type === "Boolean" && typeof value !== "boolean" ? value != null : value;
  if (!transform || !props_definition[prop]) {
    return value;
  } else if (transform === "toAttribute") {
    switch (type) {
      case "Object":
      case "Array":
        return value == null ? null : JSON.stringify(value);
      case "Boolean":
        return value ? "" : null;
      case "Number":
        return value == null ? null : value;
      default:
        return value;
    }
  } else {
    switch (type) {
      case "Object":
      case "Array":
        return value && JSON.parse(value);
      case "Boolean":
        return value;
      // conversion already handled above
      case "Number":
        return value != null ? +value : value;
      default:
        return value;
    }
  }
}
var SvelteComponent = class {
  /**
   * ### PRIVATE API
   *
   * Do not use, may change at any time
   *
   * @type {any}
   */
  $$ = void 0;
  /**
   * ### PRIVATE API
   *
   * Do not use, may change at any time
   *
   * @type {any}
   */
  $$set = void 0;
  /** @returns {void} */
  $destroy() {
    destroy_component(this, 1);
    this.$destroy = noop;
  }
  /**
   * @template {Extract<keyof Events, string>} K
   * @param {K} type
   * @param {((e: Events[K]) => void) | null | undefined} callback
   * @returns {() => void}
   */
  $on(type, callback) {
    if (!is_function(callback)) {
      return noop;
    }
    const callbacks = this.$$.callbacks[type] || (this.$$.callbacks[type] = []);
    callbacks.push(callback);
    return () => {
      const index4 = callbacks.indexOf(callback);
      if (index4 !== -1) callbacks.splice(index4, 1);
    };
  }
  /**
   * @param {Partial<Props>} props
   * @returns {void}
   */
  $set(props) {
    if (this.$$set && !is_empty(props)) {
      this.$$.skip_bound = true;
      this.$$set(props);
      this.$$.skip_bound = false;
    }
  }
};

// node_modules/svelte/src/runtime/store/index.js
var subscriber_queue = [];
function readable(value, start) {
  return {
    subscribe: writable(value, start).subscribe
  };
}
function writable(value, start = noop) {
  let stop;
  const subscribers = /* @__PURE__ */ new Set();
  function set(new_value) {
    if (safe_not_equal(value, new_value)) {
      value = new_value;
      if (stop) {
        const run_queue = !subscriber_queue.length;
        for (const subscriber of subscribers) {
          subscriber[1]();
          subscriber_queue.push(subscriber, value);
        }
        if (run_queue) {
          for (let i = 0; i < subscriber_queue.length; i += 2) {
            subscriber_queue[i][0](subscriber_queue[i + 1]);
          }
          subscriber_queue.length = 0;
        }
      }
    }
  }
  function update2(fn) {
    set(fn(value));
  }
  function subscribe2(run2, invalidate = noop) {
    const subscriber = [run2, invalidate];
    subscribers.add(subscriber);
    if (subscribers.size === 1) {
      stop = start(set, update2) || noop;
    }
    run2(value);
    return () => {
      subscribers.delete(subscriber);
      if (subscribers.size === 0 && stop) {
        stop();
        stop = null;
      }
    };
  }
  return { set, update: update2, subscribe: subscribe2 };
}
function derived(stores, fn, initial_value) {
  const single = !Array.isArray(stores);
  const stores_array = single ? [stores] : stores;
  if (!stores_array.every(Boolean)) {
    throw new Error("derived() expects stores as input, got a falsy value");
  }
  const auto = fn.length < 2;
  return readable(initial_value, (set, update2) => {
    let started = false;
    const values = [];
    let pending = 0;
    let cleanup = noop;
    const sync = () => {
      if (pending) {
        return;
      }
      cleanup();
      const result = fn(single ? values[0] : values, set, update2);
      if (auto) {
        set(result);
      } else {
        cleanup = is_function(result) ? result : noop;
      }
    };
    const unsubscribers = stores_array.map(
      (store, i) => subscribe(
        store,
        (value) => {
          values[i] = value;
          pending &= ~(1 << i);
          if (started) {
            sync();
          }
        },
        () => {
          pending |= 1 << i;
        }
      )
    );
    started = true;
    sync();
    return function stop() {
      run_all(unsubscribers);
      cleanup();
      started = false;
    };
  });
}

// node_modules/@event-calendar/core/index.js
function keyEnter(fn) {
  return function(e) {
    return e.key === "Enter" || e.key === " " && !e.preventDefault() ? fn.call(this, e) : void 0;
  };
}
function setContent(node, content) {
  let actions = {
    update(content2) {
      if (typeof content2 == "string") {
        node.innerText = content2;
      } else if (content2?.domNodes) {
        node.replaceChildren(...content2.domNodes);
      } else if (content2?.html) {
        node.innerHTML = content2.html;
      }
    }
  };
  actions.update(content);
  return actions;
}
function outsideEvent(node, type) {
  const handlePointerDown = (jsEvent) => {
    if (node && !node.contains(jsEvent.target)) {
      node.dispatchEvent(
        new CustomEvent(type + "outside", { detail: { jsEvent } })
      );
    }
  };
  document.addEventListener(type, handlePointerDown, true);
  return {
    destroy() {
      document.removeEventListener(type, handlePointerDown, true);
    }
  };
}
var DAY_IN_SECONDS = 86400;
function createDate(input = void 0) {
  if (input !== void 0) {
    return input instanceof Date ? _fromLocalDate(input) : _fromISOString(input);
  }
  return _fromLocalDate(/* @__PURE__ */ new Date());
}
function createDuration(input) {
  if (typeof input === "number") {
    input = { seconds: input };
  } else if (typeof input === "string") {
    let seconds = 0, exp = 2;
    for (let part of input.split(":", 3)) {
      seconds += parseInt(part, 10) * Math.pow(60, exp--);
    }
    input = { seconds };
  } else if (input instanceof Date) {
    input = { hours: input.getUTCHours(), minutes: input.getUTCMinutes(), seconds: input.getUTCSeconds() };
  }
  let weeks = input.weeks || input.week || 0;
  return {
    years: input.years || input.year || 0,
    months: input.months || input.month || 0,
    days: weeks * 7 + (input.days || input.day || 0),
    seconds: (input.hours || input.hour || 0) * 60 * 60 + (input.minutes || input.minute || 0) * 60 + (input.seconds || input.second || 0),
    inWeeks: !!weeks
  };
}
function cloneDate(date) {
  return new Date(date.getTime());
}
function addDuration(date, duration, x = 1) {
  date.setUTCFullYear(date.getUTCFullYear() + x * duration.years);
  let month = date.getUTCMonth() + x * duration.months;
  date.setUTCMonth(month);
  month %= 12;
  if (month < 0) {
    month += 12;
  }
  while (date.getUTCMonth() !== month) {
    subtractDay(date);
  }
  date.setUTCDate(date.getUTCDate() + x * duration.days);
  date.setUTCSeconds(date.getUTCSeconds() + x * duration.seconds);
  return date;
}
function subtractDuration(date, duration, x = 1) {
  return addDuration(date, duration, -x);
}
function addDay(date, x = 1) {
  date.setUTCDate(date.getUTCDate() + x);
  return date;
}
function subtractDay(date, x = 1) {
  return addDay(date, -x);
}
function setMidnight(date) {
  date.setUTCHours(0, 0, 0, 0);
  return date;
}
function toLocalDate(date) {
  return new Date(
    date.getUTCFullYear(),
    date.getUTCMonth(),
    date.getUTCDate(),
    date.getUTCHours(),
    date.getUTCMinutes(),
    date.getUTCSeconds()
  );
}
function toISOString(date, len = 19) {
  return date.toISOString().substring(0, len);
}
function datesEqual(date1, ...dates2) {
  return dates2.every((date2) => date1.getTime() === date2.getTime());
}
function nextClosestDay(date, day) {
  let diff2 = day - date.getUTCDay();
  date.setUTCDate(date.getUTCDate() + (diff2 >= 0 ? diff2 : diff2 + 7));
  return date;
}
function prevClosestDay(date, day) {
  let diff2 = day - date.getUTCDay();
  date.setUTCDate(date.getUTCDate() + (diff2 <= 0 ? diff2 : diff2 - 7));
  return date;
}
function noTimePart(date) {
  return typeof date === "string" && date.length <= 10;
}
function copyTime(toDate, fromDate) {
  toDate.setUTCHours(fromDate.getUTCHours(), fromDate.getUTCMinutes(), fromDate.getUTCSeconds(), 0);
  return toDate;
}
function _fromLocalDate(date) {
  return new Date(Date.UTC(
    date.getFullYear(),
    date.getMonth(),
    date.getDate(),
    date.getHours(),
    date.getMinutes(),
    date.getSeconds()
  ));
}
function _fromISOString(str) {
  const parts = str.match(/\d+/g);
  return new Date(Date.UTC(
    Number(parts[0]),
    Number(parts[1]) - 1,
    Number(parts[2]),
    Number(parts[3] || 0),
    Number(parts[4] || 0),
    Number(parts[5] || 0)
  ));
}
function debounce(fn, handle, queueStore) {
  queueStore.update((queue) => queue.set(handle, fn));
}
function flushDebounce(queue) {
  run_all(queue);
  queue.clear();
}
function task(fn, handle, tasks) {
  handle ??= fn;
  if (!tasks.has(handle)) {
    tasks.set(handle, setTimeout(() => {
      tasks.delete(handle);
      fn();
    }));
  }
}
function assign2(...args) {
  return Object.assign(...args);
}
function keys(object) {
  return Object.keys(object);
}
function floor(value) {
  return Math.floor(value);
}
function min(...args) {
  return Math.min(...args);
}
function max(...args) {
  return Math.max(...args);
}
function symbol() {
  return Symbol("ec");
}
function createElement(tag, className, content, attrs = []) {
  let el = document.createElement(tag);
  el.className = className;
  if (typeof content == "string") {
    el.innerText = content;
  } else if (content.domNodes) {
    el.replaceChildren(...content.domNodes);
  } else if (content.html) {
    el.innerHTML = content.html;
  }
  for (let attr2 of attrs) {
    el.setAttribute(...attr2);
  }
  return el;
}
function hasYScroll(el) {
  return el.scrollHeight > el.clientHeight;
}
function rect(el) {
  return el.getBoundingClientRect();
}
function ancestor(el, up) {
  while (up--) {
    el = el.parentElement;
  }
  return el;
}
function height(el) {
  return rect(el).height;
}
var payloadProp = symbol();
function setPayload(el, payload) {
  el[payloadProp] = payload;
}
function hasPayload(el) {
  return !!el?.[payloadProp];
}
function getPayload(el) {
  return el[payloadProp];
}
function getElementWithPayload(x, y, root = document) {
  for (let el of root.elementsFromPoint(x, y)) {
    if (hasPayload(el)) {
      return el;
    }
    if (el.shadowRoot) {
      let shadowEl = getElementWithPayload(x, y, el.shadowRoot);
      if (shadowEl) {
        return shadowEl;
      }
    }
  }
  return null;
}
function createView(view2, _viewTitle, _currentRange, _activeRange) {
  return {
    type: view2,
    title: _viewTitle,
    currentStart: _currentRange.start,
    currentEnd: _currentRange.end,
    activeStart: _activeRange.start,
    activeEnd: _activeRange.end,
    calendar: void 0
  };
}
function toViewWithLocalDates(view2) {
  view2 = assign2({}, view2);
  view2.currentStart = toLocalDate(view2.currentStart);
  view2.currentEnd = toLocalDate(view2.currentEnd);
  view2.activeStart = toLocalDate(view2.activeStart);
  view2.activeEnd = toLocalDate(view2.activeEnd);
  return view2;
}
function listView(view2) {
  return view2.startsWith("list");
}
var eventId = 1;
function createEvents(input) {
  return input.map((event) => ({
    id: "id" in event ? String(event.id) : `{generated-${eventId++}}`,
    resourceIds: Array.isArray(event.resourceIds) ? event.resourceIds.map(String) : "resourceId" in event ? [String(event.resourceId)] : [],
    allDay: event.allDay ?? (noTimePart(event.start) && noTimePart(event.end)),
    start: createDate(event.start),
    end: createDate(event.end),
    title: event.title || "",
    titleHTML: event.titleHTML || "",
    editable: event.editable,
    startEditable: event.startEditable,
    durationEditable: event.durationEditable,
    display: event.display || "auto",
    extendedProps: event.extendedProps || {},
    backgroundColor: event.backgroundColor || event.color,
    textColor: event.textColor
  }));
}
function createEventSources(input) {
  return input.map((source) => ({
    events: source.events,
    url: source.url && source.url.trimEnd("&") || "",
    method: source.method && source.method.toUpperCase() || "GET",
    extraParams: source.extraParams || {}
  }));
}
function createEventChunk(event, start, end) {
  return {
    start: event.start > start ? event.start : start,
    end: event.end < end ? event.end : end,
    event
  };
}
function sortEventChunks(chunks) {
  chunks.sort((a, b) => a.start - b.start || b.event.allDay - a.event.allDay);
}
function createEventContent(chunk, displayEventEnd, eventContent, theme, _intlEventTime, _view) {
  let timeText = _intlEventTime.formatRange(
    chunk.start,
    displayEventEnd && chunk.event.display !== "pointer" ? copyTime(cloneDate(chunk.start), chunk.end) : chunk.start
  );
  let content;
  if (eventContent) {
    content = is_function(eventContent) ? eventContent({
      event: toEventWithLocalDates(chunk.event),
      timeText,
      view: toViewWithLocalDates(_view)
    }) : eventContent;
  } else {
    let domNodes;
    switch (chunk.event.display) {
      case "background":
        domNodes = [];
        break;
      case "pointer":
        domNodes = [createTimeElement(timeText, chunk, theme)];
        break;
      default:
        domNodes = [
          ...chunk.event.allDay ? [] : [createTimeElement(timeText, chunk, theme)],
          createElement("h4", theme.eventTitle, chunk.event.title)
        ];
    }
    content = { domNodes };
  }
  return [timeText, content];
}
function createTimeElement(timeText, chunk, theme) {
  return createElement(
    "time",
    theme.eventTime,
    timeText,
    [["datetime", toISOString(chunk.start)]]
  );
}
function createEventClasses(eventClassNames, event, _view) {
  if (eventClassNames) {
    if (is_function(eventClassNames)) {
      eventClassNames = eventClassNames({
        event: toEventWithLocalDates(event),
        view: toViewWithLocalDates(_view)
      });
    }
    return Array.isArray(eventClassNames) ? eventClassNames : [eventClassNames];
  }
  return [];
}
function toEventWithLocalDates(event) {
  return _cloneEvent(event, toLocalDate);
}
function _cloneEvent(event, dateFn) {
  event = assign2({}, event);
  event.start = dateFn(event.start);
  event.end = dateFn(event.end);
  return event;
}
function prepareEventChunks(chunks, hiddenDays) {
  let longChunks = {};
  if (chunks.length) {
    sortEventChunks(chunks);
    let prevChunk;
    for (let chunk of chunks) {
      let dates = [];
      let date = setMidnight(cloneDate(chunk.start));
      while (chunk.end > date) {
        if (!hiddenDays.includes(date.getUTCDay())) {
          dates.push(cloneDate(date));
          if (dates.length > 1) {
            let key = date.getTime();
            if (longChunks[key]) {
              longChunks[key].chunks.push(chunk);
            } else {
              longChunks[key] = {
                sorted: false,
                chunks: [chunk]
              };
            }
          }
        }
        addDay(date);
      }
      if (dates.length) {
        chunk.date = dates[0];
        chunk.days = dates.length;
        chunk.dates = dates;
        if (chunk.start < dates[0]) {
          chunk.start = dates[0];
        }
        if (setMidnight(cloneDate(chunk.end)) > dates[dates.length - 1]) {
          chunk.end = dates[dates.length - 1];
        }
      } else {
        chunk.date = setMidnight(cloneDate(chunk.start));
        chunk.days = 1;
        chunk.dates = [chunk.date];
      }
      if (prevChunk && datesEqual(prevChunk.date, chunk.date)) {
        chunk.prev = prevChunk;
      }
      prevChunk = chunk;
    }
  }
  return longChunks;
}
function repositionEvent(chunk, longChunks, height2) {
  chunk.top = 0;
  if (chunk.prev) {
    chunk.top = chunk.prev.bottom + 1;
  }
  chunk.bottom = chunk.top + height2;
  let margin = 1;
  let key = chunk.date.getTime();
  if (longChunks[key]?.sorted || longChunks[key]?.chunks.every((chunk2) => "top" in chunk2)) {
    if (!longChunks[key].sorted) {
      longChunks[key].chunks.sort((a, b) => a.top - b.top);
      longChunks[key].sorted = true;
    }
    for (let longChunk of longChunks[key].chunks) {
      if (chunk.top < longChunk.bottom && chunk.bottom > longChunk.top) {
        let offset = longChunk.bottom - chunk.top + 1;
        margin += offset;
        chunk.top += offset;
        chunk.bottom += offset;
      }
    }
  }
  return margin;
}
function runReposition(refs, data) {
  refs.length = data.length;
  for (let ref of refs) {
    ref?.reposition?.();
  }
}
function eventIntersects(event, start, end, resource, timeMode) {
  return (event.start < end && event.end > start || !timeMode && datesEqual(event.start, event.end, start)) && (resource === void 0 || event.resourceIds.includes(resource.id));
}
function helperEvent(display) {
  return previewEvent(display) || ghostEvent(display) || pointerEvent(display);
}
function bgEvent(display) {
  return display === "background";
}
function previewEvent(display) {
  return display === "preview";
}
function ghostEvent(display) {
  return display === "ghost";
}
function pointerEvent(display) {
  return display === "pointer";
}
function btnTextDay(text2) {
  return btnText(text2, "day");
}
function btnTextWeek(text2) {
  return btnText(text2, "week");
}
function btnTextMonth(text2) {
  return btnText(text2, "month");
}
function btnTextYear(text2) {
  return btnText(text2, "year");
}
function btnText(text2, period) {
  return {
    ...text2,
    next: "Next " + period,
    prev: "Previous " + period
  };
}
function themeView(view2) {
  return (theme) => ({ ...theme, view: view2 });
}
function intl(locale, format) {
  return derived([locale, format], ([$locale, $format]) => {
    let intl2 = is_function($format) ? { format: $format } : new Intl.DateTimeFormat($locale, $format);
    return {
      format: (date) => intl2.format(toLocalDate(date))
    };
  });
}
function intlRange(locale, format) {
  return derived([locale, format], ([$locale, $format]) => {
    let formatRange;
    if (is_function($format)) {
      formatRange = $format;
    } else {
      let intl2 = new Intl.DateTimeFormat($locale, $format);
      formatRange = (start, end) => {
        if (start <= end) {
          return intl2.formatRange(start, end);
        } else {
          let parts = intl2.formatRangeToParts(end, start);
          let result = "";
          let sources = ["startRange", "endRange"];
          let processed = [false, false];
          for (let part of parts) {
            let i = sources.indexOf(part.source);
            if (i >= 0) {
              if (!processed[i]) {
                result += _getParts(sources[1 - i], parts);
                processed[i] = true;
              }
            } else {
              result += part.value;
            }
          }
          return result;
        }
      };
    }
    return {
      formatRange: (start, end) => formatRange(toLocalDate(start), toLocalDate(end))
    };
  });
}
function _getParts(source, parts) {
  let result = "";
  for (let part of parts) {
    if (part.source == source) {
      result += part.value;
    }
  }
  return result;
}
function createOptions(plugins) {
  let options = {
    allDayContent: void 0,
    allDaySlot: true,
    buttonText: {
      today: "today"
    },
    customButtons: {},
    date: /* @__PURE__ */ new Date(),
    datesSet: void 0,
    dayHeaderFormat: {
      weekday: "short",
      month: "numeric",
      day: "numeric"
    },
    dayHeaderAriaLabelFormat: {
      dateStyle: "long"
    },
    displayEventEnd: true,
    duration: { weeks: 1 },
    events: [],
    eventAllUpdated: void 0,
    eventBackgroundColor: void 0,
    eventTextColor: void 0,
    eventClassNames: void 0,
    eventClick: void 0,
    eventColor: void 0,
    eventContent: void 0,
    eventDidMount: void 0,
    eventMouseEnter: void 0,
    eventMouseLeave: void 0,
    eventSources: [],
    eventTimeFormat: {
      hour: "numeric",
      minute: "2-digit"
    },
    firstDay: 0,
    flexibleSlotTimeLimits: false,
    // ec option
    headerToolbar: {
      start: "title",
      center: "",
      end: "today prev,next"
    },
    height: void 0,
    hiddenDays: [],
    highlightedDates: [],
    // ec option
    lazyFetching: true,
    loading: void 0,
    locale: void 0,
    nowIndicator: false,
    selectable: false,
    scrollTime: "06:00:00",
    slotDuration: "00:30:00",
    slotEventOverlap: true,
    slotHeight: 24,
    // ec option
    slotLabelFormat: {
      hour: "numeric",
      minute: "2-digit"
    },
    slotMaxTime: "24:00:00",
    slotMinTime: "00:00:00",
    theme: {
      allDay: "ec-all-day",
      active: "ec-active",
      bgEvent: "ec-bg-event",
      bgEvents: "ec-bg-events",
      body: "ec-body",
      button: "ec-button",
      buttonGroup: "ec-button-group",
      calendar: "ec",
      compact: "ec-compact",
      content: "ec-content",
      day: "ec-day",
      dayHead: "ec-day-head",
      days: "ec-days",
      event: "ec-event",
      eventBody: "ec-event-body",
      eventTime: "ec-event-time",
      eventTitle: "ec-event-title",
      events: "ec-events",
      extra: "ec-extra",
      handle: "ec-handle",
      header: "ec-header",
      hiddenScroll: "ec-hidden-scroll",
      highlight: "ec-highlight",
      icon: "ec-icon",
      line: "ec-line",
      lines: "ec-lines",
      nowIndicator: "ec-now-indicator",
      otherMonth: "ec-other-month",
      sidebar: "ec-sidebar",
      sidebarTitle: "ec-sidebar-title",
      today: "ec-today",
      time: "ec-time",
      title: "ec-title",
      toolbar: "ec-toolbar",
      view: "",
      weekdays: ["ec-sun", "ec-mon", "ec-tue", "ec-wed", "ec-thu", "ec-fri", "ec-sat"],
      withScroll: "ec-with-scroll"
    },
    titleFormat: {
      year: "numeric",
      month: "short",
      day: "numeric"
    },
    view: void 0,
    viewDidMount: void 0,
    views: {}
  };
  for (let plugin of plugins) {
    plugin.createOptions?.(options);
  }
  return options;
}
function createParsers(plugins) {
  let parsers = {
    date: (date) => setMidnight(createDate(date)),
    duration: createDuration,
    events: createEvents,
    eventSources: createEventSources,
    hiddenDays: (days2) => [...new Set(days2)],
    highlightedDates: (dates) => dates.map(createDate),
    scrollTime: createDuration,
    slotDuration: createDuration,
    slotMaxTime: createDuration,
    slotMinTime: createDuration
  };
  for (let plugin of plugins) {
    plugin.createParsers?.(parsers);
  }
  return parsers;
}
function diff(options, prevOptions) {
  let diff2 = [];
  for (let key of keys(options)) {
    if (options[key] !== prevOptions[key]) {
      diff2.push([key, options[key]]);
    }
  }
  assign2(prevOptions, options);
  return diff2;
}
function dayGrid(state) {
  return derived(state.view, ($view) => $view?.startsWith("dayGrid"));
}
function activeRange(state) {
  return derived(
    [state._currentRange, state.firstDay, state.slotMaxTime, state._dayGrid],
    ([$_currentRange, $firstDay, $slotMaxTime, $_dayGrid]) => {
      let start = cloneDate($_currentRange.start);
      let end = cloneDate($_currentRange.end);
      if ($_dayGrid) {
        prevClosestDay(start, $firstDay);
        nextClosestDay(end, $firstDay);
      } else if ($slotMaxTime.days || $slotMaxTime.seconds > DAY_IN_SECONDS) {
        addDuration(subtractDay(end), $slotMaxTime);
        let start2 = subtractDay(cloneDate(end));
        if (start2 < start) {
          start = start2;
        }
      }
      return { start, end };
    }
  );
}
function currentRange(state) {
  return derived(
    [state.date, state.duration, state.firstDay, state._dayGrid],
    ([$date, $duration, $firstDay, $_dayGrid]) => {
      let start = cloneDate($date), end;
      if ($_dayGrid) {
        start.setUTCDate(1);
      } else if ($duration.inWeeks) {
        prevClosestDay(start, $firstDay);
      }
      end = addDuration(cloneDate(start), $duration);
      return { start, end };
    }
  );
}
function viewDates(state) {
  return derived([state._activeRange, state.hiddenDays], ([$_activeRange, $hiddenDays]) => {
    let dates = [];
    let date = setMidnight(cloneDate($_activeRange.start));
    let end = setMidnight(cloneDate($_activeRange.end));
    while (date < end) {
      if (!$hiddenDays.includes(date.getUTCDay())) {
        dates.push(cloneDate(date));
      }
      addDay(date);
    }
    if (!dates.length && $hiddenDays.length && $hiddenDays.length < 7) {
      state.date.update((date2) => {
        while ($hiddenDays.includes(date2.getUTCDay())) {
          addDay(date2);
        }
        return date2;
      });
      dates = get_store_value(state._viewDates);
    }
    return dates;
  });
}
function viewTitle(state) {
  return derived(
    [state.date, state._activeRange, state._intlTitle, state._dayGrid],
    ([$date, $_activeRange, $_intlTitle, $_dayGrid]) => {
      return $_dayGrid ? $_intlTitle.formatRange($date, $date) : $_intlTitle.formatRange($_activeRange.start, subtractDay(cloneDate($_activeRange.end)));
    }
  );
}
function view(state) {
  return derived([state.view, state._viewTitle, state._currentRange, state._activeRange], (args) => createView(...args));
}
function events(state) {
  let _events = writable([]);
  let abortController;
  let fetching = 0;
  let debounceHandle = {};
  derived(
    [state.events, state.eventSources, state._activeRange, state._fetchedRange, state.lazyFetching, state.loading],
    (values, set) => debounce(() => {
      let [$events, $eventSources, $_activeRange, $_fetchedRange, $lazyFetching, $loading] = values;
      if (!$eventSources.length) {
        set($events);
        return;
      }
      if (!$_fetchedRange.start || $_fetchedRange.start > $_activeRange.start || $_fetchedRange.end < $_activeRange.end || !$lazyFetching) {
        if (abortController) {
          abortController.abort();
        }
        abortController = new AbortController();
        if (is_function($loading) && !fetching) {
          $loading(true);
        }
        let stopLoading = () => {
          if (--fetching === 0 && is_function($loading)) {
            $loading(false);
          }
        };
        let events2 = [];
        let failure = (e) => stopLoading();
        let success = (data) => {
          events2 = events2.concat(createEvents(data));
          set(events2);
          stopLoading();
        };
        let startStr = toISOString($_activeRange.start);
        let endStr = toISOString($_activeRange.end);
        for (let source of $eventSources) {
          if (is_function(source.events)) {
            let result = source.events({
              start: toLocalDate($_activeRange.start),
              end: toLocalDate($_activeRange.end),
              startStr,
              endStr
            }, success, failure);
            if (result !== void 0) {
              Promise.resolve(result).then(success, failure);
            }
          } else {
            let params = is_function(source.extraParams) ? source.extraParams() : assign2({}, source.extraParams);
            params.start = startStr;
            params.end = endStr;
            params = new URLSearchParams(params);
            let url = source.url, headers = {}, body;
            if (["GET", "HEAD"].includes(source.method)) {
              url += (url.includes("?") ? "&" : "?") + params;
            } else {
              headers["content-type"] = "application/x-www-form-urlencoded;charset=UTF-8";
              body = String(params);
            }
            fetch(url, { method: source.method, headers, body, signal: abortController.signal, credentials: "same-origin" }).then((response) => response.json()).then(success).catch(failure);
          }
          ++fetching;
        }
        $_fetchedRange.start = $_activeRange.start;
        $_fetchedRange.end = $_activeRange.end;
      }
    }, debounceHandle, state._queue),
    []
  ).subscribe(_events.set);
  return _events;
}
function now2() {
  return readable(createDate(), (set) => {
    let interval = setInterval(() => {
      set(createDate());
    }, 1e3);
    return () => clearInterval(interval);
  });
}
function today(state) {
  return derived(state._now, ($_now) => setMidnight(cloneDate($_now)));
}
var State = class {
  constructor(plugins, input) {
    plugins = plugins || [];
    let options = createOptions(plugins);
    let parsers = createParsers(plugins);
    options = parseOpts(options, parsers);
    input = parseOpts(input, parsers);
    for (let [option, value] of Object.entries(options)) {
      this[option] = writable(value);
    }
    this._queue = writable(/* @__PURE__ */ new Map());
    this._queue2 = writable(/* @__PURE__ */ new Map());
    this._tasks = /* @__PURE__ */ new Map();
    this._auxiliary = writable([]);
    this._dayGrid = dayGrid(this);
    this._currentRange = currentRange(this);
    this._activeRange = activeRange(this);
    this._fetchedRange = writable({ start: void 0, end: void 0 });
    this._events = events(this);
    this._now = now2();
    this._today = today(this);
    this._intlEventTime = intlRange(this.locale, this.eventTimeFormat);
    this._intlSlotLabel = intl(this.locale, this.slotLabelFormat);
    this._intlDayHeader = intl(this.locale, this.dayHeaderFormat);
    this._intlDayHeaderAL = intl(this.locale, this.dayHeaderAriaLabelFormat);
    this._intlTitle = intlRange(this.locale, this.titleFormat);
    this._bodyEl = writable(void 0);
    this._scrollable = writable(false);
    this._viewTitle = viewTitle(this);
    this._viewDates = viewDates(this);
    this._view = view(this);
    this._viewComponent = writable(void 0);
    this._resBgColor = writable(noop);
    this._resTxtColor = writable(noop);
    this._interaction = writable({});
    this._iEvents = writable([null, null]);
    this._iClasses = writable(identity);
    this._iClass = writable(void 0);
    this._set = (key, value) => {
      if (validKey(key, this)) {
        if (parsers[key]) {
          value = parsers[key](value);
        }
        this[key].set(value);
      }
    };
    this._get = (key) => validKey(key, this) ? get_store_value(this[key]) : void 0;
    for (let plugin of plugins) {
      plugin.createStores?.(this);
    }
    if (input.view) {
      this.view.set(input.view);
    }
    let views = /* @__PURE__ */ new Set([...keys(options.views), ...keys(input.views ?? {})]);
    for (let view2 of views) {
      let defOpts = mergeOpts(options, options.views[view2] ?? {});
      let opts = mergeOpts(defOpts, input, input.views?.[view2] ?? {});
      let component = opts.component;
      filterOpts(opts, this);
      for (let key of keys(opts)) {
        let { set, _set = set, ...rest } = this[key];
        this[key] = {
          // Set value in all views
          set: ["buttonText", "theme"].includes(key) ? (value) => {
            if (is_function(value)) {
              let result = value(defOpts[key]);
              opts[key] = result;
              set(set === _set ? result : value);
            } else {
              opts[key] = value;
              set(value);
            }
          } : (value) => {
            opts[key] = value;
            set(value);
          },
          _set,
          ...rest
        };
      }
      this.view.subscribe((newView) => {
        if (newView === view2) {
          this._viewComponent.set(component);
          if (is_function(opts.viewDidMount)) {
            tick().then(() => opts.viewDidMount(get_store_value(this._view)));
          }
          for (let key of keys(opts)) {
            this[key]._set(opts[key]);
          }
        }
      });
    }
  }
};
function parseOpts(opts, parsers) {
  let result = { ...opts };
  for (let key of keys(parsers)) {
    if (key in result) {
      result[key] = parsers[key](result[key]);
    }
  }
  if (opts.views) {
    result.views = {};
    for (let view2 of keys(opts.views)) {
      result.views[view2] = parseOpts(opts.views[view2], parsers);
    }
  }
  return result;
}
function mergeOpts(...args) {
  let result = {};
  for (let opts of args) {
    let override = {};
    for (let key of ["buttonText", "theme"]) {
      if (is_function(opts[key])) {
        override[key] = opts[key](result[key]);
      }
    }
    result = {
      ...result,
      ...opts,
      ...override
    };
  }
  return result;
}
function filterOpts(opts, state) {
  keys(opts).filter((key) => !validKey(key, state) || key == "view").forEach((key) => delete opts[key]);
}
function validKey(key, state) {
  return state.hasOwnProperty(key) && key[0] !== "_";
}
function get_each_context$2(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[25] = list[i];
  return child_ctx;
}
function create_if_block_5(ctx) {
  let button_1;
  let t_value = (
    /*$buttonText*/
    ctx[5][
      /*button*/
      ctx[25]
    ] + ""
  );
  let t;
  let button_1_class_value;
  let mounted;
  let dispose;
  function click_handler_1() {
    return (
      /*click_handler_1*/
      ctx[22](
        /*button*/
        ctx[25]
      )
    );
  }
  return {
    c() {
      button_1 = element("button");
      t = text(t_value);
      attr(button_1, "class", button_1_class_value = /*$theme*/
      ctx[3].button + /*$view*/
      (ctx[7] === /*button*/
      ctx[25] ? " " + /*$theme*/
      ctx[3].active : "") + " ec-" + /*button*/
      ctx[25]);
    },
    m(target, anchor) {
      insert(target, button_1, anchor);
      append(button_1, t);
      if (!mounted) {
        dispose = listen(button_1, "click", click_handler_1);
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (dirty & /*$buttonText, buttons*/
      33 && t_value !== (t_value = /*$buttonText*/
      ctx[5][
        /*button*/
        ctx[25]
      ] + "")) set_data(t, t_value);
      if (dirty & /*$theme, $view, buttons*/
      137 && button_1_class_value !== (button_1_class_value = /*$theme*/
      ctx[3].button + /*$view*/
      (ctx[7] === /*button*/
      ctx[25] ? " " + /*$theme*/
      ctx[3].active : "") + " ec-" + /*button*/
      ctx[25])) {
        attr(button_1, "class", button_1_class_value);
      }
    },
    d(detaching) {
      if (detaching) {
        detach(button_1);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_if_block_4(ctx) {
  let button_1;
  let t_value = (
    /*$customButtons*/
    ctx[6][
      /*button*/
      ctx[25]
    ].text + ""
  );
  let t;
  let button_1_class_value;
  let mounted;
  let dispose;
  return {
    c() {
      button_1 = element("button");
      t = text(t_value);
      attr(button_1, "class", button_1_class_value = /*$theme*/
      ctx[3].button + " ec-" + /*button*/
      ctx[25]);
    },
    m(target, anchor) {
      insert(target, button_1, anchor);
      append(button_1, t);
      if (!mounted) {
        dispose = listen(button_1, "click", function() {
          if (is_function(
            /*$customButtons*/
            ctx[6][
              /*button*/
              ctx[25]
            ].click
          )) ctx[6][
            /*button*/
            ctx[25]
          ].click.apply(this, arguments);
        });
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (dirty & /*$customButtons, buttons*/
      65 && t_value !== (t_value = /*$customButtons*/
      ctx[6][
        /*button*/
        ctx[25]
      ].text + "")) set_data(t, t_value);
      if (dirty & /*$theme, buttons*/
      9 && button_1_class_value !== (button_1_class_value = /*$theme*/
      ctx[3].button + " ec-" + /*button*/
      ctx[25])) {
        attr(button_1, "class", button_1_class_value);
      }
    },
    d(detaching) {
      if (detaching) {
        detach(button_1);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_if_block_3(ctx) {
  let button_1;
  let t_value = (
    /*$buttonText*/
    ctx[5][
      /*button*/
      ctx[25]
    ] + ""
  );
  let t;
  let button_1_class_value;
  let mounted;
  let dispose;
  return {
    c() {
      button_1 = element("button");
      t = text(t_value);
      attr(button_1, "class", button_1_class_value = /*$theme*/
      ctx[3].button + " ec-" + /*button*/
      ctx[25]);
      button_1.disabled = /*isToday*/
      ctx[1];
    },
    m(target, anchor) {
      insert(target, button_1, anchor);
      append(button_1, t);
      if (!mounted) {
        dispose = listen(
          button_1,
          "click",
          /*click_handler*/
          ctx[21]
        );
        mounted = true;
      }
    },
    p(ctx2, dirty) {
      if (dirty & /*$buttonText, buttons*/
      33 && t_value !== (t_value = /*$buttonText*/
      ctx2[5][
        /*button*/
        ctx2[25]
      ] + "")) set_data(t, t_value);
      if (dirty & /*$theme, buttons*/
      9 && button_1_class_value !== (button_1_class_value = /*$theme*/
      ctx2[3].button + " ec-" + /*button*/
      ctx2[25])) {
        attr(button_1, "class", button_1_class_value);
      }
      if (dirty & /*isToday*/
      2) {
        button_1.disabled = /*isToday*/
        ctx2[1];
      }
    },
    d(detaching) {
      if (detaching) {
        detach(button_1);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_if_block_2(ctx) {
  let button_1;
  let i;
  let i_class_value;
  let button_1_class_value;
  let button_1_aria_label_value;
  let button_1_title_value;
  let mounted;
  let dispose;
  return {
    c() {
      button_1 = element("button");
      i = element("i");
      attr(i, "class", i_class_value = /*$theme*/
      ctx[3].icon + " ec-" + /*button*/
      ctx[25]);
      attr(button_1, "class", button_1_class_value = /*$theme*/
      ctx[3].button + " ec-" + /*button*/
      ctx[25]);
      attr(button_1, "aria-label", button_1_aria_label_value = /*$buttonText*/
      ctx[5].next);
      attr(button_1, "title", button_1_title_value = /*$buttonText*/
      ctx[5].next);
    },
    m(target, anchor) {
      insert(target, button_1, anchor);
      append(button_1, i);
      if (!mounted) {
        dispose = listen(
          button_1,
          "click",
          /*next*/
          ctx[19]
        );
        mounted = true;
      }
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme, buttons*/
      9 && i_class_value !== (i_class_value = /*$theme*/
      ctx2[3].icon + " ec-" + /*button*/
      ctx2[25])) {
        attr(i, "class", i_class_value);
      }
      if (dirty & /*$theme, buttons*/
      9 && button_1_class_value !== (button_1_class_value = /*$theme*/
      ctx2[3].button + " ec-" + /*button*/
      ctx2[25])) {
        attr(button_1, "class", button_1_class_value);
      }
      if (dirty & /*$buttonText*/
      32 && button_1_aria_label_value !== (button_1_aria_label_value = /*$buttonText*/
      ctx2[5].next)) {
        attr(button_1, "aria-label", button_1_aria_label_value);
      }
      if (dirty & /*$buttonText*/
      32 && button_1_title_value !== (button_1_title_value = /*$buttonText*/
      ctx2[5].next)) {
        attr(button_1, "title", button_1_title_value);
      }
    },
    d(detaching) {
      if (detaching) {
        detach(button_1);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_if_block_1(ctx) {
  let button_1;
  let i;
  let i_class_value;
  let button_1_class_value;
  let button_1_aria_label_value;
  let button_1_title_value;
  let mounted;
  let dispose;
  return {
    c() {
      button_1 = element("button");
      i = element("i");
      attr(i, "class", i_class_value = /*$theme*/
      ctx[3].icon + " ec-" + /*button*/
      ctx[25]);
      attr(button_1, "class", button_1_class_value = /*$theme*/
      ctx[3].button + " ec-" + /*button*/
      ctx[25]);
      attr(button_1, "aria-label", button_1_aria_label_value = /*$buttonText*/
      ctx[5].prev);
      attr(button_1, "title", button_1_title_value = /*$buttonText*/
      ctx[5].prev);
    },
    m(target, anchor) {
      insert(target, button_1, anchor);
      append(button_1, i);
      if (!mounted) {
        dispose = listen(
          button_1,
          "click",
          /*prev*/
          ctx[18]
        );
        mounted = true;
      }
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme, buttons*/
      9 && i_class_value !== (i_class_value = /*$theme*/
      ctx2[3].icon + " ec-" + /*button*/
      ctx2[25])) {
        attr(i, "class", i_class_value);
      }
      if (dirty & /*$theme, buttons*/
      9 && button_1_class_value !== (button_1_class_value = /*$theme*/
      ctx2[3].button + " ec-" + /*button*/
      ctx2[25])) {
        attr(button_1, "class", button_1_class_value);
      }
      if (dirty & /*$buttonText*/
      32 && button_1_aria_label_value !== (button_1_aria_label_value = /*$buttonText*/
      ctx2[5].prev)) {
        attr(button_1, "aria-label", button_1_aria_label_value);
      }
      if (dirty & /*$buttonText*/
      32 && button_1_title_value !== (button_1_title_value = /*$buttonText*/
      ctx2[5].prev)) {
        attr(button_1, "title", button_1_title_value);
      }
    },
    d(detaching) {
      if (detaching) {
        detach(button_1);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_if_block$1(ctx) {
  let h2;
  let h2_class_value;
  let setContent_action;
  let mounted;
  let dispose;
  return {
    c() {
      h2 = element("h2");
      attr(h2, "class", h2_class_value = /*$theme*/
      ctx[3].title);
    },
    m(target, anchor) {
      insert(target, h2, anchor);
      if (!mounted) {
        dispose = action_destroyer(setContent_action = setContent.call(
          null,
          h2,
          /*$_viewTitle*/
          ctx[4]
        ));
        mounted = true;
      }
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme*/
      8 && h2_class_value !== (h2_class_value = /*$theme*/
      ctx2[3].title)) {
        attr(h2, "class", h2_class_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*$_viewTitle*/
      16) setContent_action.update.call(
        null,
        /*$_viewTitle*/
        ctx2[4]
      );
    },
    d(detaching) {
      if (detaching) {
        detach(h2);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_each_block$2(ctx) {
  let if_block_anchor;
  function select_block_type(ctx2, dirty) {
    if (
      /*button*/
      ctx2[25] == "title"
    ) return create_if_block$1;
    if (
      /*button*/
      ctx2[25] == "prev"
    ) return create_if_block_1;
    if (
      /*button*/
      ctx2[25] == "next"
    ) return create_if_block_2;
    if (
      /*button*/
      ctx2[25] == "today"
    ) return create_if_block_3;
    if (
      /*$customButtons*/
      ctx2[6][
        /*button*/
        ctx2[25]
      ]
    ) return create_if_block_4;
    if (
      /*button*/
      ctx2[25] != ""
    ) return create_if_block_5;
  }
  let current_block_type = select_block_type(ctx);
  let if_block = current_block_type && current_block_type(ctx);
  return {
    c() {
      if (if_block) if_block.c();
      if_block_anchor = empty();
    },
    m(target, anchor) {
      if (if_block) if_block.m(target, anchor);
      insert(target, if_block_anchor, anchor);
    },
    p(ctx2, dirty) {
      if (current_block_type === (current_block_type = select_block_type(ctx2)) && if_block) {
        if_block.p(ctx2, dirty);
      } else {
        if (if_block) if_block.d(1);
        if_block = current_block_type && current_block_type(ctx2);
        if (if_block) {
          if_block.c();
          if_block.m(if_block_anchor.parentNode, if_block_anchor);
        }
      }
    },
    d(detaching) {
      if (detaching) {
        detach(if_block_anchor);
      }
      if (if_block) {
        if_block.d(detaching);
      }
    }
  };
}
function create_fragment$3(ctx) {
  let each_1_anchor;
  let each_value = ensure_array_like(
    /*buttons*/
    ctx[0]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block$2(get_each_context$2(ctx, each_value, i));
  }
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
    },
    p(ctx2, [dirty]) {
      if (dirty & /*$theme, $_viewTitle, buttons, $buttonText, prev, next, isToday, $date, today, $customButtons, $view*/
      917759) {
        each_value = ensure_array_like(
          /*buttons*/
          ctx2[0]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context$2(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
          } else {
            each_blocks[i] = create_each_block$2(child_ctx);
            each_blocks[i].c();
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        for (; i < each_blocks.length; i += 1) {
          each_blocks[i].d(1);
        }
        each_blocks.length = each_value.length;
      }
    },
    i: noop,
    o: noop,
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function instance$3($$self, $$props, $$invalidate) {
  let $duration;
  let $date;
  let $hiddenDays;
  let $_currentRange;
  let $theme;
  let $_viewTitle;
  let $buttonText;
  let $customButtons;
  let $view;
  let { buttons } = $$props;
  let { _currentRange, _viewTitle, buttonText, customButtons, date, duration, hiddenDays, theme, view: view2 } = getContext("state");
  component_subscribe($$self, _currentRange, (value) => $$invalidate(20, $_currentRange = value));
  component_subscribe($$self, _viewTitle, (value) => $$invalidate(4, $_viewTitle = value));
  component_subscribe($$self, buttonText, (value) => $$invalidate(5, $buttonText = value));
  component_subscribe($$self, customButtons, (value) => $$invalidate(6, $customButtons = value));
  component_subscribe($$self, date, (value) => $$invalidate(2, $date = value));
  component_subscribe($$self, duration, (value) => $$invalidate(23, $duration = value));
  component_subscribe($$self, hiddenDays, (value) => $$invalidate(24, $hiddenDays = value));
  component_subscribe($$self, theme, (value) => $$invalidate(3, $theme = value));
  component_subscribe($$self, view2, (value) => $$invalidate(7, $view = value));
  let today2 = setMidnight(createDate()), isToday;
  function prev() {
    let d = subtractDuration($date, $duration);
    if ($hiddenDays.length && $hiddenDays.length < 7) {
      while ($hiddenDays.includes(d.getUTCDay())) {
        subtractDay(d);
      }
    }
    set_store_value(date, $date = d, $date);
  }
  function next() {
    set_store_value(date, $date = addDuration($date, $duration), $date);
  }
  const click_handler = () => set_store_value(date, $date = cloneDate(today2), $date);
  const click_handler_1 = (button) => set_store_value(view2, $view = button, $view);
  $$self.$$set = ($$props2) => {
    if ("buttons" in $$props2) $$invalidate(0, buttons = $$props2.buttons);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*$_currentRange*/
    1048576) {
      $$invalidate(1, isToday = today2 >= $_currentRange.start && today2 < $_currentRange.end || null);
    }
  };
  return [
    buttons,
    isToday,
    $date,
    $theme,
    $_viewTitle,
    $buttonText,
    $customButtons,
    $view,
    _currentRange,
    _viewTitle,
    buttonText,
    customButtons,
    date,
    duration,
    hiddenDays,
    theme,
    view2,
    today2,
    prev,
    next,
    $_currentRange,
    click_handler,
    click_handler_1
  ];
}
var Buttons = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$3, create_fragment$3, safe_not_equal, { buttons: 0 });
  }
};
function get_each_context$1(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[5] = list[i];
  return child_ctx;
}
function get_each_context_1(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[8] = list[i];
  return child_ctx;
}
function create_else_block(ctx) {
  let buttons_1;
  let current;
  buttons_1 = new Buttons({ props: { buttons: (
    /*buttons*/
    ctx[8]
  ) } });
  return {
    c() {
      create_component(buttons_1.$$.fragment);
    },
    m(target, anchor) {
      mount_component(buttons_1, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      const buttons_1_changes = {};
      if (dirty & /*sections*/
      1) buttons_1_changes.buttons = /*buttons*/
      ctx2[8];
      buttons_1.$set(buttons_1_changes);
    },
    i(local) {
      if (current) return;
      transition_in(buttons_1.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(buttons_1.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(buttons_1, detaching);
    }
  };
}
function create_if_block(ctx) {
  let div;
  let buttons_1;
  let div_class_value;
  let current;
  buttons_1 = new Buttons({ props: { buttons: (
    /*buttons*/
    ctx[8]
  ) } });
  return {
    c() {
      div = element("div");
      create_component(buttons_1.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[1].buttonGroup);
    },
    m(target, anchor) {
      insert(target, div, anchor);
      mount_component(buttons_1, div, null);
      current = true;
    },
    p(ctx2, dirty) {
      const buttons_1_changes = {};
      if (dirty & /*sections*/
      1) buttons_1_changes.buttons = /*buttons*/
      ctx2[8];
      buttons_1.$set(buttons_1_changes);
      if (!current || dirty & /*$theme*/
      2 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[1].buttonGroup)) {
        attr(div, "class", div_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(buttons_1.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(buttons_1.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      destroy_component(buttons_1);
    }
  };
}
function create_each_block_1(ctx) {
  let current_block_type_index;
  let if_block;
  let if_block_anchor;
  let current;
  const if_block_creators = [create_if_block, create_else_block];
  const if_blocks = [];
  function select_block_type(ctx2, dirty) {
    if (
      /*buttons*/
      ctx2[8].length > 1
    ) return 0;
    return 1;
  }
  current_block_type_index = select_block_type(ctx);
  if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
  return {
    c() {
      if_block.c();
      if_block_anchor = empty();
    },
    m(target, anchor) {
      if_blocks[current_block_type_index].m(target, anchor);
      insert(target, if_block_anchor, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      let previous_block_index = current_block_type_index;
      current_block_type_index = select_block_type(ctx2);
      if (current_block_type_index === previous_block_index) {
        if_blocks[current_block_type_index].p(ctx2, dirty);
      } else {
        group_outros();
        transition_out(if_blocks[previous_block_index], 1, 1, () => {
          if_blocks[previous_block_index] = null;
        });
        check_outros();
        if_block = if_blocks[current_block_type_index];
        if (!if_block) {
          if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx2);
          if_block.c();
        } else {
          if_block.p(ctx2, dirty);
        }
        transition_in(if_block, 1);
        if_block.m(if_block_anchor.parentNode, if_block_anchor);
      }
    },
    i(local) {
      if (current) return;
      transition_in(if_block);
      current = true;
    },
    o(local) {
      transition_out(if_block);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(if_block_anchor);
      }
      if_blocks[current_block_type_index].d(detaching);
    }
  };
}
function create_each_block$1(ctx) {
  let div;
  let t;
  let current;
  let each_value_1 = ensure_array_like(
    /*sections*/
    ctx[0][
      /*key*/
      ctx[5]
    ]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value_1.length; i += 1) {
    each_blocks[i] = create_each_block_1(get_each_context_1(ctx, each_value_1, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      div = element("div");
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      t = space();
    },
    m(target, anchor) {
      insert(target, div, anchor);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div, null);
        }
      }
      append(div, t);
      current = true;
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme, sections, Object*/
      3) {
        each_value_1 = ensure_array_like(
          /*sections*/
          ctx2[0][
            /*key*/
            ctx2[5]
          ]
        );
        let i;
        for (i = 0; i < each_value_1.length; i += 1) {
          const child_ctx = get_each_context_1(ctx2, each_value_1, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block_1(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(div, t);
          }
        }
        group_outros();
        for (i = each_value_1.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value_1.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function create_fragment$2(ctx) {
  let nav;
  let nav_class_value;
  let current;
  let each_value = ensure_array_like(Object.keys(
    /*sections*/
    ctx[0]
  ));
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block$1(get_each_context$1(ctx, each_value, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      nav = element("nav");
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      attr(nav, "class", nav_class_value = /*$theme*/
      ctx[1].toolbar);
    },
    m(target, anchor) {
      insert(target, nav, anchor);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(nav, null);
        }
      }
      current = true;
    },
    p(ctx2, [dirty]) {
      if (dirty & /*sections, Object, $theme*/
      3) {
        each_value = ensure_array_like(Object.keys(
          /*sections*/
          ctx2[0]
        ));
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context$1(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block$1(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(nav, null);
          }
        }
        group_outros();
        for (i = each_value.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
      if (!current || dirty & /*$theme*/
      2 && nav_class_value !== (nav_class_value = /*$theme*/
      ctx2[1].toolbar)) {
        attr(nav, "class", nav_class_value);
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(nav);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function instance$2($$self, $$props, $$invalidate) {
  let $headerToolbar;
  let $theme;
  let { headerToolbar, theme } = getContext("state");
  component_subscribe($$self, headerToolbar, (value) => $$invalidate(4, $headerToolbar = value));
  component_subscribe($$self, theme, (value) => $$invalidate(1, $theme = value));
  let sections = { start: [], center: [], end: [] };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*sections, $headerToolbar*/
    17) {
      {
        for (let key of Object.keys(sections)) {
          $$invalidate(0, sections[key] = $headerToolbar[key].split(" ").map((group) => group.split(",")), sections);
        }
      }
    }
  };
  return [sections, $theme, headerToolbar, theme, $headerToolbar];
}
var Toolbar = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$2, create_fragment$2, safe_not_equal, {});
  }
};
function get_each_context(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[11] = list[i];
  return child_ctx;
}
function create_each_block(ctx) {
  let switch_instance;
  let switch_instance_anchor;
  let current;
  var switch_value = (
    /*component*/
    ctx[11]
  );
  function switch_props(ctx2, dirty) {
    return {};
  }
  if (switch_value) {
    switch_instance = construct_svelte_component(switch_value, switch_props());
  }
  return {
    c() {
      if (switch_instance) create_component(switch_instance.$$.fragment);
      switch_instance_anchor = empty();
    },
    m(target, anchor) {
      if (switch_instance) mount_component(switch_instance, target, anchor);
      insert(target, switch_instance_anchor, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      if (dirty & /*$_auxiliary*/
      1 && switch_value !== (switch_value = /*component*/
      ctx2[11])) {
        if (switch_instance) {
          group_outros();
          const old_component = switch_instance;
          transition_out(old_component.$$.fragment, 1, 0, () => {
            destroy_component(old_component, 1);
          });
          check_outros();
        }
        if (switch_value) {
          switch_instance = construct_svelte_component(switch_value, switch_props());
          create_component(switch_instance.$$.fragment);
          transition_in(switch_instance.$$.fragment, 1);
          mount_component(switch_instance, switch_instance_anchor.parentNode, switch_instance_anchor);
        } else {
          switch_instance = null;
        }
      }
    },
    i(local) {
      if (current) return;
      if (switch_instance) transition_in(switch_instance.$$.fragment, local);
      current = true;
    },
    o(local) {
      if (switch_instance) transition_out(switch_instance.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(switch_instance_anchor);
      }
      if (switch_instance) destroy_component(switch_instance, detaching);
    }
  };
}
function create_fragment$1(ctx) {
  let each_1_anchor;
  let current;
  let each_value = ensure_array_like(
    /*$_auxiliary*/
    ctx[0]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block(get_each_context(ctx, each_value, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
      current = true;
    },
    p(ctx2, [dirty]) {
      if (dirty & /*$_auxiliary*/
      1) {
        each_value = ensure_array_like(
          /*$_auxiliary*/
          ctx2[0]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        group_outros();
        for (i = each_value.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function instance$1($$self, $$props, $$invalidate) {
  let $_view;
  let $datesSet;
  let $_activeRange;
  let $_auxiliary;
  let { datesSet, _auxiliary, _activeRange, _queue, _view } = getContext("state");
  component_subscribe($$self, datesSet, (value) => $$invalidate(7, $datesSet = value));
  component_subscribe($$self, _auxiliary, (value) => $$invalidate(0, $_auxiliary = value));
  component_subscribe($$self, _activeRange, (value) => $$invalidate(5, $_activeRange = value));
  component_subscribe($$self, _view, (value) => $$invalidate(6, $_view = value));
  let debounceHandle = {};
  function runDatesSet(_activeRange2) {
    if (is_function($datesSet)) {
      debounce(
        () => $datesSet({
          start: toLocalDate(_activeRange2.start),
          end: toLocalDate(_activeRange2.end),
          startStr: toISOString(_activeRange2.start),
          endStr: toISOString(_activeRange2.end),
          view: toViewWithLocalDates($_view)
        }),
        debounceHandle,
        _queue
      );
    }
  }
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*$_activeRange*/
    32) {
      runDatesSet($_activeRange);
    }
  };
  return [$_auxiliary, datesSet, _auxiliary, _activeRange, _view, $_activeRange];
}
var Auxiliary = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$1, create_fragment$1, safe_not_equal, {});
  }
};
function create_fragment(ctx) {
  let div;
  let toolbar;
  let t0;
  let switch_instance;
  let div_class_value;
  let div_role_value;
  let t1;
  let auxiliary;
  let current;
  let mounted;
  let dispose;
  toolbar = new Toolbar({});
  var switch_value = (
    /*$_viewComponent*/
    ctx[5]
  );
  function switch_props(ctx2, dirty) {
    return {};
  }
  if (switch_value) {
    switch_instance = construct_svelte_component(switch_value, switch_props());
  }
  auxiliary = new Auxiliary({});
  return {
    c() {
      div = element("div");
      create_component(toolbar.$$.fragment);
      t0 = space();
      if (switch_instance) create_component(switch_instance.$$.fragment);
      t1 = space();
      create_component(auxiliary.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[1].calendar + " " + /*$theme*/
      ctx[1].view + /*$_scrollable*/
      (ctx[0] ? " " + /*$theme*/
      ctx[1].withScroll : "") + /*$_iClass*/
      (ctx[2] ? " " + /*$theme*/
      ctx[1][
        /*$_iClass*/
        ctx[2]
      ] : ""));
      attr(div, "role", div_role_value = listView(
        /*$view*/
        ctx[4]
      ) ? "list" : "table");
      set_style(
        div,
        "height",
        /*$height*/
        ctx[3]
      );
    },
    m(target, anchor) {
      insert(target, div, anchor);
      mount_component(toolbar, div, null);
      append(div, t0);
      if (switch_instance) mount_component(switch_instance, div, null);
      insert(target, t1, anchor);
      mount_component(auxiliary, target, anchor);
      current = true;
      if (!mounted) {
        dispose = listen(
          window,
          "resize",
          /*recheckScrollable*/
          ctx[17]
        );
        mounted = true;
      }
    },
    p(ctx2, dirty) {
      if (dirty[0] & /*$_viewComponent*/
      32 && switch_value !== (switch_value = /*$_viewComponent*/
      ctx2[5])) {
        if (switch_instance) {
          group_outros();
          const old_component = switch_instance;
          transition_out(old_component.$$.fragment, 1, 0, () => {
            destroy_component(old_component, 1);
          });
          check_outros();
        }
        if (switch_value) {
          switch_instance = construct_svelte_component(switch_value, switch_props());
          create_component(switch_instance.$$.fragment);
          transition_in(switch_instance.$$.fragment, 1);
          mount_component(switch_instance, div, null);
        } else {
          switch_instance = null;
        }
      }
      if (!current || dirty[0] & /*$theme, $_scrollable, $_iClass*/
      7 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[1].calendar + " " + /*$theme*/
      ctx2[1].view + /*$_scrollable*/
      (ctx2[0] ? " " + /*$theme*/
      ctx2[1].withScroll : "") + /*$_iClass*/
      (ctx2[2] ? " " + /*$theme*/
      ctx2[1][
        /*$_iClass*/
        ctx2[2]
      ] : ""))) {
        attr(div, "class", div_class_value);
      }
      if (!current || dirty[0] & /*$view*/
      16 && div_role_value !== (div_role_value = listView(
        /*$view*/
        ctx2[4]
      ) ? "list" : "table")) {
        attr(div, "role", div_role_value);
      }
      if (dirty[0] & /*$height*/
      8) {
        set_style(
          div,
          "height",
          /*$height*/
          ctx2[3]
        );
      }
    },
    i(local) {
      if (current) return;
      transition_in(toolbar.$$.fragment, local);
      if (switch_instance) transition_in(switch_instance.$$.fragment, local);
      transition_in(auxiliary.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(toolbar.$$.fragment, local);
      if (switch_instance) transition_out(switch_instance.$$.fragment, local);
      transition_out(auxiliary.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
        detach(t1);
      }
      destroy_component(toolbar);
      if (switch_instance) destroy_component(switch_instance);
      destroy_component(auxiliary, detaching);
      mounted = false;
      dispose();
    }
  };
}
function instance($$self, $$props, $$invalidate) {
  let $_bodyEl;
  let $_scrollable;
  let $_queue2;
  let $_queue;
  let $_interaction;
  let $_events;
  let $theme;
  let $_iClass;
  let $height;
  let $view;
  let $_viewComponent;
  let { plugins = [] } = $$props;
  let { options = {} } = $$props;
  let component = get_current_component();
  let state = new State(plugins, options);
  setContext("state", state);
  let { _viewComponent, _bodyEl, _interaction, _iClass, _events, _queue, _queue2, _tasks, _scrollable, height: height2, theme, view: view2 } = state;
  component_subscribe($$self, _viewComponent, (value) => $$invalidate(5, $_viewComponent = value));
  component_subscribe($$self, _bodyEl, (value) => $$invalidate(32, $_bodyEl = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(35, $_interaction = value));
  component_subscribe($$self, _iClass, (value) => $$invalidate(2, $_iClass = value));
  component_subscribe($$self, _events, (value) => $$invalidate(36, $_events = value));
  component_subscribe($$self, _queue, (value) => $$invalidate(34, $_queue = value));
  component_subscribe($$self, _queue2, (value) => $$invalidate(33, $_queue2 = value));
  component_subscribe($$self, _scrollable, (value) => $$invalidate(0, $_scrollable = value));
  component_subscribe($$self, height2, (value) => $$invalidate(3, $height = value));
  component_subscribe($$self, theme, (value) => $$invalidate(1, $theme = value));
  component_subscribe($$self, view2, (value) => $$invalidate(4, $view = value));
  let prevOptions = { ...options };
  function setOption(name, value) {
    state._set(name, value);
    return this;
  }
  function getOption(name) {
    let value = state._get(name);
    return value instanceof Date ? toLocalDate(value) : value;
  }
  function refetchEvents() {
    state._fetchedRange.set({ start: void 0, end: void 0 });
    return this;
  }
  function getEvents() {
    return $_events.map(toEventWithLocalDates);
  }
  function getEventById(id) {
    for (let event of $_events) {
      if (event.id == id) {
        return toEventWithLocalDates(event);
      }
    }
    return null;
  }
  function addEvent(event) {
    $_events.push(createEvents([event])[0]);
    _events.set($_events);
    return this;
  }
  function updateEvent(event) {
    for (let e of $_events) {
      if (e.id == event.id) {
        assign2(e, createEvents([event])[0]);
        _events.set($_events);
        break;
      }
    }
    return this;
  }
  function removeEventById(id) {
    let idx = $_events.findIndex((event) => event.id == id);
    if (idx >= 0) {
      $_events.splice(idx, 1);
      _events.set($_events);
    }
    return this;
  }
  function getView() {
    return toViewWithLocalDates(get_store_value(state._view));
  }
  function unselect() {
    if ($_interaction.action) {
      $_interaction.action.unselect();
    }
    return this;
  }
  function dateFromPoint(x, y) {
    let dayEl = getElementWithPayload(x, y);
    return dayEl ? getPayload(dayEl)(y) : null;
  }
  function destroy() {
    destroy_component(component, true);
  }
  beforeUpdate(() => {
    flushDebounce($_queue);
  });
  afterUpdate(() => {
    flushDebounce($_queue2);
    task(recheckScrollable, null, _tasks);
  });
  function recheckScrollable() {
    if ($_bodyEl) {
      set_store_value(_scrollable, $_scrollable = hasYScroll($_bodyEl), $_scrollable);
    }
  }
  $$self.$$set = ($$props2) => {
    if ("plugins" in $$props2) $$invalidate(18, plugins = $$props2.plugins);
    if ("options" in $$props2) $$invalidate(19, options = $$props2.options);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty[0] & /*options*/
    524288) {
      for (let [name, value] of diff(options, prevOptions)) {
        setOption(name, value);
      }
    }
  };
  return [
    $_scrollable,
    $theme,
    $_iClass,
    $height,
    $view,
    $_viewComponent,
    _viewComponent,
    _bodyEl,
    _interaction,
    _iClass,
    _events,
    _queue,
    _queue2,
    _scrollable,
    height2,
    theme,
    view2,
    recheckScrollable,
    plugins,
    options,
    setOption,
    getOption,
    refetchEvents,
    getEvents,
    getEventById,
    addEvent,
    updateEvent,
    removeEventById,
    getView,
    unselect,
    dateFromPoint,
    destroy
  ];
}
var Calendar = class extends SvelteComponent {
  constructor(options) {
    super();
    init(
      this,
      options,
      instance,
      create_fragment,
      safe_not_equal,
      {
        plugins: 18,
        options: 19,
        setOption: 20,
        getOption: 21,
        refetchEvents: 22,
        getEvents: 23,
        getEventById: 24,
        addEvent: 25,
        updateEvent: 26,
        removeEventById: 27,
        getView: 28,
        unselect: 29,
        dateFromPoint: 30,
        destroy: 31
      },
      null,
      [-1, -1]
    );
  }
  get setOption() {
    return this.$$.ctx[20];
  }
  get getOption() {
    return this.$$.ctx[21];
  }
  get refetchEvents() {
    return this.$$.ctx[22];
  }
  get getEvents() {
    return this.$$.ctx[23];
  }
  get getEventById() {
    return this.$$.ctx[24];
  }
  get addEvent() {
    return this.$$.ctx[25];
  }
  get updateEvent() {
    return this.$$.ctx[26];
  }
  get removeEventById() {
    return this.$$.ctx[27];
  }
  get getView() {
    return this.$$.ctx[28];
  }
  get unselect() {
    return this.$$.ctx[29];
  }
  get dateFromPoint() {
    return this.$$.ctx[30];
  }
  get destroy() {
    return this.$$.ctx[31];
  }
};

// node_modules/@event-calendar/day-grid/index.js
function days(state) {
  return derived([state.date, state.firstDay, state.hiddenDays], ([$date, $firstDay, $hiddenDays]) => {
    let days2 = [];
    let day = cloneDate($date);
    let max2 = 7;
    while (day.getUTCDay() !== $firstDay && max2) {
      subtractDay(day);
      --max2;
    }
    for (let i = 0; i < 7; ++i) {
      if (!$hiddenDays.includes(day.getUTCDay())) {
        days2.push(cloneDate(day));
      }
      addDay(day);
    }
    return days2;
  });
}
function get_each_context$4(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[8] = list[i];
  return child_ctx;
}
function create_each_block$4(ctx) {
  let div;
  let span;
  let span_aria_label_value;
  let setContent_action;
  let t;
  let div_class_value;
  let mounted;
  let dispose;
  return {
    c() {
      div = element("div");
      span = element("span");
      t = space();
      attr(span, "aria-label", span_aria_label_value = /*$_intlDayHeaderAL*/
      ctx[2].format(
        /*day*/
        ctx[8]
      ));
      attr(div, "class", div_class_value = /*$theme*/
      ctx[0].day + " " + /*$theme*/
      ctx[0].weekdays?.[
        /*day*/
        ctx[8].getUTCDay()
      ]);
      attr(div, "role", "columnheader");
    },
    m(target, anchor) {
      insert(target, div, anchor);
      append(div, span);
      append(div, t);
      if (!mounted) {
        dispose = action_destroyer(setContent_action = setContent.call(
          null,
          span,
          /*$_intlDayHeader*/
          ctx[3].format(
            /*day*/
            ctx[8]
          )
        ));
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (dirty & /*$_intlDayHeaderAL, $_days*/
      6 && span_aria_label_value !== (span_aria_label_value = /*$_intlDayHeaderAL*/
      ctx[2].format(
        /*day*/
        ctx[8]
      ))) {
        attr(span, "aria-label", span_aria_label_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*$_intlDayHeader, $_days*/
      10) setContent_action.update.call(
        null,
        /*$_intlDayHeader*/
        ctx[3].format(
          /*day*/
          ctx[8]
        )
      );
      if (dirty & /*$theme, $_days*/
      3 && div_class_value !== (div_class_value = /*$theme*/
      ctx[0].day + " " + /*$theme*/
      ctx[0].weekdays?.[
        /*day*/
        ctx[8].getUTCDay()
      ])) {
        attr(div, "class", div_class_value);
      }
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_fragment$6(ctx) {
  let div2;
  let div0;
  let div0_class_value;
  let t;
  let div1;
  let div1_class_value;
  let div2_class_value;
  let each_value = ensure_array_like(
    /*$_days*/
    ctx[1]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block$4(get_each_context$4(ctx, each_value, i));
  }
  return {
    c() {
      div2 = element("div");
      div0 = element("div");
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      t = space();
      div1 = element("div");
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[0].days);
      attr(div0, "role", "row");
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[0].hiddenScroll);
      attr(div2, "class", div2_class_value = /*$theme*/
      ctx[0].header);
    },
    m(target, anchor) {
      insert(target, div2, anchor);
      append(div2, div0);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div0, null);
        }
      }
      append(div2, t);
      append(div2, div1);
    },
    p(ctx2, [dirty]) {
      if (dirty & /*$theme, $_days, $_intlDayHeaderAL, $_intlDayHeader*/
      15) {
        each_value = ensure_array_like(
          /*$_days*/
          ctx2[1]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context$4(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
          } else {
            each_blocks[i] = create_each_block$4(child_ctx);
            each_blocks[i].c();
            each_blocks[i].m(div0, null);
          }
        }
        for (; i < each_blocks.length; i += 1) {
          each_blocks[i].d(1);
        }
        each_blocks.length = each_value.length;
      }
      if (dirty & /*$theme*/
      1 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[0].days)) {
        attr(div0, "class", div0_class_value);
      }
      if (dirty & /*$theme*/
      1 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[0].hiddenScroll)) {
        attr(div1, "class", div1_class_value);
      }
      if (dirty & /*$theme*/
      1 && div2_class_value !== (div2_class_value = /*$theme*/
      ctx2[0].header)) {
        attr(div2, "class", div2_class_value);
      }
    },
    i: noop,
    o: noop,
    d(detaching) {
      if (detaching) {
        detach(div2);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function instance$6($$self, $$props, $$invalidate) {
  let $theme;
  let $_days;
  let $_intlDayHeaderAL;
  let $_intlDayHeader;
  let { theme, _intlDayHeader, _intlDayHeaderAL, _days } = getContext("state");
  component_subscribe($$self, theme, (value) => $$invalidate(0, $theme = value));
  component_subscribe($$self, _intlDayHeader, (value) => $$invalidate(3, $_intlDayHeader = value));
  component_subscribe($$self, _intlDayHeaderAL, (value) => $$invalidate(2, $_intlDayHeaderAL = value));
  component_subscribe($$self, _days, (value) => $$invalidate(1, $_days = value));
  return [
    $theme,
    $_days,
    $_intlDayHeaderAL,
    $_intlDayHeader,
    theme,
    _intlDayHeader,
    _intlDayHeaderAL,
    _days
  ];
}
var Header = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$6, create_fragment$6, safe_not_equal, {});
  }
};
function create_fragment$5(ctx) {
  let div1;
  let div0;
  let div0_class_value;
  let div1_class_value;
  let current;
  const default_slot_template = (
    /*#slots*/
    ctx[7].default
  );
  const default_slot = create_slot(
    default_slot_template,
    ctx,
    /*$$scope*/
    ctx[6],
    null
  );
  return {
    c() {
      div1 = element("div");
      div0 = element("div");
      if (default_slot) default_slot.c();
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[0].content);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[0].body + /*$dayMaxEvents*/
      (ctx[1] === true ? " " + /*$theme*/
      ctx[0].uniform : ""));
    },
    m(target, anchor) {
      insert(target, div1, anchor);
      append(div1, div0);
      if (default_slot) {
        default_slot.m(div0, null);
      }
      ctx[8](div1);
      current = true;
    },
    p(ctx2, [dirty]) {
      if (default_slot) {
        if (default_slot.p && (!current || dirty & /*$$scope*/
        64)) {
          update_slot_base(
            default_slot,
            default_slot_template,
            ctx2,
            /*$$scope*/
            ctx2[6],
            !current ? get_all_dirty_from_scope(
              /*$$scope*/
              ctx2[6]
            ) : get_slot_changes(
              default_slot_template,
              /*$$scope*/
              ctx2[6],
              dirty,
              null
            ),
            null
          );
        }
      }
      if (!current || dirty & /*$theme*/
      1 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[0].content)) {
        attr(div0, "class", div0_class_value);
      }
      if (!current || dirty & /*$theme, $dayMaxEvents*/
      3 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[0].body + /*$dayMaxEvents*/
      (ctx2[1] === true ? " " + /*$theme*/
      ctx2[0].uniform : ""))) {
        attr(div1, "class", div1_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(default_slot, local);
      current = true;
    },
    o(local) {
      transition_out(default_slot, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div1);
      }
      if (default_slot) default_slot.d(detaching);
      ctx[8](null);
    }
  };
}
function instance$5($$self, $$props, $$invalidate) {
  let $theme;
  let $dayMaxEvents;
  let $_bodyEl;
  let { $$slots: slots = {}, $$scope } = $$props;
  let { dayMaxEvents, _bodyEl, theme } = getContext("state");
  component_subscribe($$self, dayMaxEvents, (value) => $$invalidate(1, $dayMaxEvents = value));
  component_subscribe($$self, _bodyEl, (value) => $$invalidate(2, $_bodyEl = value));
  component_subscribe($$self, theme, (value) => $$invalidate(0, $theme = value));
  function div1_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      $_bodyEl = $$value;
      _bodyEl.set($_bodyEl);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("$$scope" in $$props2) $$invalidate(6, $$scope = $$props2.$$scope);
  };
  return [
    $theme,
    $dayMaxEvents,
    $_bodyEl,
    dayMaxEvents,
    _bodyEl,
    theme,
    $$scope,
    slots,
    div1_binding
  ];
}
var Body = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$5, create_fragment$5, safe_not_equal, {});
  }
};
function create_fragment$4(ctx) {
  let article;
  let div;
  let div_class_value;
  let setContent_action;
  let t;
  let switch_instance;
  let article_role_value;
  let article_tabindex_value;
  let current;
  let mounted;
  let dispose;
  var switch_value = (
    /*$_interaction*/
    ctx[8].resizer
  );
  function switch_props(ctx2, dirty) {
    return { props: { event: (
      /*event*/
      ctx2[0]
    ) } };
  }
  if (switch_value) {
    switch_instance = construct_svelte_component(switch_value, switch_props(ctx));
    switch_instance.$on("pointerdown", function() {
      if (is_function(
        /*createDragHandler*/
        ctx[33](
          /*$_interaction*/
          ctx[8],
          true
        )
      )) ctx[33](
        /*$_interaction*/
        ctx[8],
        true
      ).apply(this, arguments);
    });
  }
  return {
    c() {
      article = element("article");
      div = element("div");
      t = space();
      if (switch_instance) create_component(switch_instance.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[2].eventBody);
      attr(
        article,
        "class",
        /*classes*/
        ctx[4]
      );
      attr(
        article,
        "style",
        /*style*/
        ctx[5]
      );
      attr(article, "role", article_role_value = /*onclick*/
      ctx[7] ? "button" : void 0);
      attr(article, "tabindex", article_tabindex_value = /*onclick*/
      ctx[7] ? 0 : void 0);
    },
    m(target, anchor) {
      insert(target, article, anchor);
      append(article, div);
      append(article, t);
      if (switch_instance) mount_component(switch_instance, article, null);
      ctx[52](article);
      current = true;
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            div,
            /*content*/
            ctx[6]
          )),
          listen(article, "click", function() {
            if (is_function(
              /*onclick*/
              ctx[7] || void 0
            )) /*onclick*/
            (ctx[7] || void 0).apply(this, arguments);
          }),
          listen(article, "keydown", function() {
            if (is_function(
              /*onclick*/
              ctx[7] && keyEnter(
                /*onclick*/
                ctx[7]
              )
            )) /*onclick*/
            (ctx[7] && keyEnter(
              /*onclick*/
              ctx[7]
            )).apply(this, arguments);
          }),
          listen(article, "mouseenter", function() {
            if (is_function(
              /*createHandler*/
              ctx[32](
                /*$eventMouseEnter*/
                ctx[9],
                /*display*/
                ctx[1]
              )
            )) ctx[32](
              /*$eventMouseEnter*/
              ctx[9],
              /*display*/
              ctx[1]
            ).apply(this, arguments);
          }),
          listen(article, "mouseleave", function() {
            if (is_function(
              /*createHandler*/
              ctx[32](
                /*$eventMouseLeave*/
                ctx[10],
                /*display*/
                ctx[1]
              )
            )) ctx[32](
              /*$eventMouseLeave*/
              ctx[10],
              /*display*/
              ctx[1]
            ).apply(this, arguments);
          }),
          listen(article, "pointerdown", function() {
            if (is_function(!helperEvent(
              /*display*/
              ctx[1]
            ) && /*createDragHandler*/
            ctx[33](
              /*$_interaction*/
              ctx[8]
            ))) (!helperEvent(
              /*display*/
              ctx[1]
            ) && /*createDragHandler*/
            ctx[33](
              /*$_interaction*/
              ctx[8]
            )).apply(this, arguments);
          })
        ];
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (!current || dirty[0] & /*$theme*/
      4 && div_class_value !== (div_class_value = /*$theme*/
      ctx[2].eventBody)) {
        attr(div, "class", div_class_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty[0] & /*content*/
      64) setContent_action.update.call(
        null,
        /*content*/
        ctx[6]
      );
      if (dirty[0] & /*$_interaction*/
      256 && switch_value !== (switch_value = /*$_interaction*/
      ctx[8].resizer)) {
        if (switch_instance) {
          group_outros();
          const old_component = switch_instance;
          transition_out(old_component.$$.fragment, 1, 0, () => {
            destroy_component(old_component, 1);
          });
          check_outros();
        }
        if (switch_value) {
          switch_instance = construct_svelte_component(switch_value, switch_props(ctx));
          switch_instance.$on("pointerdown", function() {
            if (is_function(
              /*createDragHandler*/
              ctx[33](
                /*$_interaction*/
                ctx[8],
                true
              )
            )) ctx[33](
              /*$_interaction*/
              ctx[8],
              true
            ).apply(this, arguments);
          });
          create_component(switch_instance.$$.fragment);
          transition_in(switch_instance.$$.fragment, 1);
          mount_component(switch_instance, article, null);
        } else {
          switch_instance = null;
        }
      } else if (switch_value) {
        const switch_instance_changes = {};
        if (dirty[0] & /*event*/
        1) switch_instance_changes.event = /*event*/
        ctx[0];
        switch_instance.$set(switch_instance_changes);
      }
      if (!current || dirty[0] & /*classes*/
      16) {
        attr(
          article,
          "class",
          /*classes*/
          ctx[4]
        );
      }
      if (!current || dirty[0] & /*style*/
      32) {
        attr(
          article,
          "style",
          /*style*/
          ctx[5]
        );
      }
      if (!current || dirty[0] & /*onclick*/
      128 && article_role_value !== (article_role_value = /*onclick*/
      ctx[7] ? "button" : void 0)) {
        attr(article, "role", article_role_value);
      }
      if (!current || dirty[0] & /*onclick*/
      128 && article_tabindex_value !== (article_tabindex_value = /*onclick*/
      ctx[7] ? 0 : void 0)) {
        attr(article, "tabindex", article_tabindex_value);
      }
    },
    i(local) {
      if (current) return;
      if (switch_instance) transition_in(switch_instance.$$.fragment, local);
      current = true;
    },
    o(local) {
      if (switch_instance) transition_out(switch_instance.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(article);
      }
      if (switch_instance) destroy_component(switch_instance);
      ctx[52](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function instance$4($$self, $$props, $$invalidate) {
  let $eventClick;
  let $_hiddenEvents;
  let $dayMaxEvents;
  let $_popupDate;
  let $_interaction;
  let $_view;
  let $eventAllUpdated;
  let $eventDidMount;
  let $_intlEventTime;
  let $theme;
  let $eventContent;
  let $displayEventEnd;
  let $eventClassNames;
  let $_iClasses;
  let $eventTextColor;
  let $_resTxtColor;
  let $eventColor;
  let $eventBackgroundColor;
  let $_resBgColor;
  let $eventMouseEnter;
  let $eventMouseLeave;
  let { chunk } = $$props;
  let { longChunks = {} } = $$props;
  let { inPopup = false } = $$props;
  let { dayMaxEvents, displayEventEnd, eventAllUpdated, eventBackgroundColor, eventTextColor, eventClick, eventColor, eventContent, eventClassNames, eventDidMount, eventMouseEnter, eventMouseLeave, theme, _view, _intlEventTime, _interaction, _iClasses, _resBgColor, _resTxtColor, _hiddenEvents, _popupDate, _tasks } = getContext("state");
  component_subscribe($$self, dayMaxEvents, (value) => $$invalidate(55, $dayMaxEvents = value));
  component_subscribe($$self, displayEventEnd, (value) => $$invalidate(44, $displayEventEnd = value));
  component_subscribe($$self, eventAllUpdated, (value) => $$invalidate(57, $eventAllUpdated = value));
  component_subscribe($$self, eventBackgroundColor, (value) => $$invalidate(50, $eventBackgroundColor = value));
  component_subscribe($$self, eventTextColor, (value) => $$invalidate(47, $eventTextColor = value));
  component_subscribe($$self, eventClick, (value) => $$invalidate(40, $eventClick = value));
  component_subscribe($$self, eventColor, (value) => $$invalidate(49, $eventColor = value));
  component_subscribe($$self, eventContent, (value) => $$invalidate(43, $eventContent = value));
  component_subscribe($$self, eventClassNames, (value) => $$invalidate(45, $eventClassNames = value));
  component_subscribe($$self, eventDidMount, (value) => $$invalidate(58, $eventDidMount = value));
  component_subscribe($$self, eventMouseEnter, (value) => $$invalidate(9, $eventMouseEnter = value));
  component_subscribe($$self, eventMouseLeave, (value) => $$invalidate(10, $eventMouseLeave = value));
  component_subscribe($$self, theme, (value) => $$invalidate(2, $theme = value));
  component_subscribe($$self, _view, (value) => $$invalidate(41, $_view = value));
  component_subscribe($$self, _intlEventTime, (value) => $$invalidate(42, $_intlEventTime = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(8, $_interaction = value));
  component_subscribe($$self, _iClasses, (value) => $$invalidate(46, $_iClasses = value));
  component_subscribe($$self, _resBgColor, (value) => $$invalidate(51, $_resBgColor = value));
  component_subscribe($$self, _resTxtColor, (value) => $$invalidate(48, $_resTxtColor = value));
  component_subscribe($$self, _hiddenEvents, (value) => $$invalidate(54, $_hiddenEvents = value));
  component_subscribe($$self, _popupDate, (value) => $$invalidate(56, $_popupDate = value));
  let el;
  let event;
  let classes;
  let style;
  let content;
  let timeText;
  let margin = 1;
  let hidden = false;
  let display;
  let onclick;
  onMount(() => {
    if (is_function($eventDidMount)) {
      $eventDidMount({
        event: toEventWithLocalDates(event),
        timeText,
        el,
        view: toViewWithLocalDates($_view)
      });
    }
  });
  afterUpdate(() => {
    if (is_function($eventAllUpdated) && !helperEvent(display)) {
      task(() => $eventAllUpdated({ view: toViewWithLocalDates($_view) }), "eau", _tasks);
    }
  });
  function createHandler(fn, display2) {
    return !helperEvent(display2) && is_function(fn) ? (jsEvent) => fn({
      event: toEventWithLocalDates(event),
      el,
      jsEvent,
      view: toViewWithLocalDates($_view)
    }) : void 0;
  }
  function createDragHandler(interaction, resize) {
    return interaction.action ? (jsEvent) => $_interaction.action.drag(event, jsEvent, resize, inPopup ? $_popupDate : void 0) : void 0;
  }
  function reposition() {
    if (!el) {
      return;
    }
    $$invalidate(38, margin = repositionEvent(chunk, longChunks, height(el)));
    if ($dayMaxEvents === true) {
      hide();
    } else {
      $$invalidate(39, hidden = false);
    }
  }
  function hide() {
    let dayEl = ancestor(el, 2);
    let h = height(dayEl) - height(dayEl.firstElementChild) - footHeight(dayEl);
    $$invalidate(39, hidden = chunk.bottom > h);
    let update2 = false;
    for (let date of chunk.dates) {
      let hiddenEvents = $_hiddenEvents[date.getTime()];
      if (hiddenEvents) {
        let size = hiddenEvents.size;
        if (hidden) {
          hiddenEvents.add(chunk.event);
        } else {
          hiddenEvents.delete(chunk.event);
        }
        if (size !== hiddenEvents.size) {
          update2 = true;
        }
      }
    }
    if (update2) {
      _hiddenEvents.set($_hiddenEvents);
    }
  }
  function footHeight(dayEl) {
    let h = 0;
    for (let i = 0; i < chunk.days; ++i) {
      h = max(h, height(dayEl.lastElementChild));
      dayEl = dayEl.nextElementSibling;
      if (!dayEl) {
        break;
      }
    }
    return h;
  }
  function article_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(3, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("chunk" in $$props2) $$invalidate(34, chunk = $$props2.chunk);
    if ("longChunks" in $$props2) $$invalidate(35, longChunks = $$props2.longChunks);
    if ("inPopup" in $$props2) $$invalidate(36, inPopup = $$props2.inPopup);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty[1] & /*chunk*/
    8) {
      $$invalidate(0, event = chunk.event);
    }
    if ($$self.$$.dirty[0] & /*event, style, $theme*/
    37 | $$self.$$.dirty[1] & /*$_resBgColor, $eventBackgroundColor, $eventColor, $_resTxtColor, $eventTextColor, chunk, margin, hidden, $_iClasses, $eventClassNames, $_view*/
    2082184) {
      {
        $$invalidate(1, display = event.display);
        let bgColor = event.backgroundColor || $_resBgColor(event) || $eventBackgroundColor || $eventColor;
        let txtColor = event.textColor || $_resTxtColor(event) || $eventTextColor;
        $$invalidate(5, style = `width:calc(${chunk.days * 100}% + ${(chunk.days - 1) * 7}px);margin-top:${margin}px;`);
        if (bgColor) {
          $$invalidate(5, style += `background-color:${bgColor};`);
        }
        if (txtColor) {
          $$invalidate(5, style += `color:${txtColor};`);
        }
        if (hidden) {
          $$invalidate(5, style += "visibility:hidden;");
        }
        $$invalidate(4, classes = [
          $theme.event,
          ...$_iClasses([], event),
          ...createEventClasses($eventClassNames, event, $_view)
        ].join(" "));
      }
    }
    if ($$self.$$.dirty[0] & /*$theme*/
    4 | $$self.$$.dirty[1] & /*chunk, $displayEventEnd, $eventContent, $_intlEventTime, $_view*/
    15368) {
      $$invalidate(6, [timeText, content] = createEventContent(chunk, $displayEventEnd, $eventContent, $theme, $_intlEventTime, $_view), content);
    }
    if ($$self.$$.dirty[0] & /*display*/
    2 | $$self.$$.dirty[1] & /*$eventClick*/
    512) {
      $$invalidate(7, onclick = createHandler($eventClick, display));
    }
  };
  return [
    event,
    display,
    $theme,
    el,
    classes,
    style,
    content,
    onclick,
    $_interaction,
    $eventMouseEnter,
    $eventMouseLeave,
    dayMaxEvents,
    displayEventEnd,
    eventAllUpdated,
    eventBackgroundColor,
    eventTextColor,
    eventClick,
    eventColor,
    eventContent,
    eventClassNames,
    eventDidMount,
    eventMouseEnter,
    eventMouseLeave,
    theme,
    _view,
    _intlEventTime,
    _interaction,
    _iClasses,
    _resBgColor,
    _resTxtColor,
    _hiddenEvents,
    _popupDate,
    createHandler,
    createDragHandler,
    chunk,
    longChunks,
    inPopup,
    reposition,
    margin,
    hidden,
    $eventClick,
    $_view,
    $_intlEventTime,
    $eventContent,
    $displayEventEnd,
    $eventClassNames,
    $_iClasses,
    $eventTextColor,
    $_resTxtColor,
    $eventColor,
    $eventBackgroundColor,
    $_resBgColor,
    article_binding
  ];
}
var Event = class extends SvelteComponent {
  constructor(options) {
    super();
    init(
      this,
      options,
      instance$4,
      create_fragment$4,
      safe_not_equal,
      {
        chunk: 34,
        longChunks: 35,
        inPopup: 36,
        reposition: 37
      },
      null,
      [-1, -1]
    );
  }
  get reposition() {
    return this.$$.ctx[37];
  }
};
function get_each_context$3(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[20] = list[i];
  return child_ctx;
}
function create_each_block$3(key_1, ctx) {
  let first;
  let event;
  let current;
  event = new Event({
    props: { chunk: (
      /*chunk*/
      ctx[20]
    ), inPopup: true }
  });
  return {
    key: key_1,
    first: null,
    c() {
      first = empty();
      create_component(event.$$.fragment);
      this.first = first;
    },
    m(target, anchor) {
      insert(target, first, anchor);
      mount_component(event, target, anchor);
      current = true;
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      const event_changes = {};
      if (dirty & /*$_popupChunks*/
      1) event_changes.chunk = /*chunk*/
      ctx[20];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(first);
      }
      destroy_component(event, detaching);
    }
  };
}
function create_fragment$32(ctx) {
  let div2;
  let div0;
  let time;
  let time_datetime_value;
  let setContent_action;
  let t0;
  let a;
  let t1;
  let a_aria_label_value;
  let div0_class_value;
  let t2;
  let div1;
  let each_blocks = [];
  let each_1_lookup = /* @__PURE__ */ new Map();
  let div1_class_value;
  let div2_class_value;
  let current;
  let mounted;
  let dispose;
  let each_value = ensure_array_like(
    /*$_popupChunks*/
    ctx[0]
  );
  const get_key = (ctx2) => (
    /*chunk*/
    ctx2[20].event
  );
  for (let i = 0; i < each_value.length; i += 1) {
    let child_ctx = get_each_context$3(ctx, each_value, i);
    let key = get_key(child_ctx);
    each_1_lookup.set(key, each_blocks[i] = create_each_block$3(key, child_ctx));
  }
  return {
    c() {
      div2 = element("div");
      div0 = element("div");
      time = element("time");
      t0 = space();
      a = element("a");
      t1 = text("\xD7");
      t2 = space();
      div1 = element("div");
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      attr(time, "datetime", time_datetime_value = toISOString(
        /*$_popupDate*/
        ctx[3],
        10
      ));
      attr(a, "role", "button");
      attr(a, "tabindex", "0");
      attr(a, "aria-label", a_aria_label_value = /*$buttonText*/
      ctx[6].close);
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[4].dayHead);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[4].events);
      attr(div2, "class", div2_class_value = /*$theme*/
      ctx[4].popup);
      attr(
        div2,
        "style",
        /*style*/
        ctx[2]
      );
    },
    m(target, anchor) {
      insert(target, div2, anchor);
      append(div2, div0);
      append(div0, time);
      append(div0, t0);
      append(div0, a);
      append(a, t1);
      append(div2, t2);
      append(div2, div1);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div1, null);
        }
      }
      ctx[16](div2);
      current = true;
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            time,
            /*$_intlDayPopover*/
            ctx[5].format(
              /*$_popupDate*/
              ctx[3]
            )
          )),
          listen(a, "click", stop_propagation(
            /*close*/
            ctx[13]
          )),
          listen(a, "keydown", keyEnter(
            /*close*/
            ctx[13]
          )),
          action_destroyer(outsideEvent.call(null, div2, "pointerdown")),
          listen(div2, "pointerdown", stop_propagation(
            /*pointerdown_handler*/
            ctx[15]
          )),
          listen(
            div2,
            "pointerdownoutside",
            /*handlePointerDownOutside*/
            ctx[14]
          )
        ];
        mounted = true;
      }
    },
    p(ctx2, [dirty]) {
      if (!current || dirty & /*$_popupDate*/
      8 && time_datetime_value !== (time_datetime_value = toISOString(
        /*$_popupDate*/
        ctx2[3],
        10
      ))) {
        attr(time, "datetime", time_datetime_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*$_intlDayPopover, $_popupDate*/
      40) setContent_action.update.call(
        null,
        /*$_intlDayPopover*/
        ctx2[5].format(
          /*$_popupDate*/
          ctx2[3]
        )
      );
      if (!current || dirty & /*$buttonText*/
      64 && a_aria_label_value !== (a_aria_label_value = /*$buttonText*/
      ctx2[6].close)) {
        attr(a, "aria-label", a_aria_label_value);
      }
      if (!current || dirty & /*$theme*/
      16 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[4].dayHead)) {
        attr(div0, "class", div0_class_value);
      }
      if (dirty & /*$_popupChunks*/
      1) {
        each_value = ensure_array_like(
          /*$_popupChunks*/
          ctx2[0]
        );
        group_outros();
        each_blocks = update_keyed_each(each_blocks, dirty, get_key, 1, ctx2, each_value, each_1_lookup, div1, outro_and_destroy_block, create_each_block$3, null, get_each_context$3);
        check_outros();
      }
      if (!current || dirty & /*$theme*/
      16 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[4].events)) {
        attr(div1, "class", div1_class_value);
      }
      if (!current || dirty & /*$theme*/
      16 && div2_class_value !== (div2_class_value = /*$theme*/
      ctx2[4].popup)) {
        attr(div2, "class", div2_class_value);
      }
      if (!current || dirty & /*style*/
      4) {
        attr(
          div2,
          "style",
          /*style*/
          ctx2[2]
        );
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div2);
      }
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].d();
      }
      ctx[16](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function instance$32($$self, $$props, $$invalidate) {
  let $_interaction;
  let $_popupDate;
  let $_popupChunks;
  let $theme;
  let $_intlDayPopover;
  let $buttonText;
  let { buttonText, theme, _interaction, _intlDayPopover, _popupDate, _popupChunks } = getContext("state");
  component_subscribe($$self, buttonText, (value) => $$invalidate(6, $buttonText = value));
  component_subscribe($$self, theme, (value) => $$invalidate(4, $theme = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(17, $_interaction = value));
  component_subscribe($$self, _intlDayPopover, (value) => $$invalidate(5, $_intlDayPopover = value));
  component_subscribe($$self, _popupDate, (value) => $$invalidate(3, $_popupDate = value));
  component_subscribe($$self, _popupChunks, (value) => $$invalidate(0, $_popupChunks = value));
  let el;
  let style = "";
  function position() {
    let dayEl = ancestor(el, 1);
    let bodyEl = ancestor(dayEl, 3);
    let popupRect = rect(el);
    let dayRect = rect(dayEl);
    let bodyRect = rect(bodyEl);
    $$invalidate(2, style = "");
    let left;
    if (popupRect.width >= bodyRect.width) {
      left = bodyRect.left - dayRect.left;
      let right = dayRect.right - bodyRect.right;
      $$invalidate(2, style += `right:${right}px;`);
    } else {
      left = (dayRect.width - popupRect.width) / 2;
      if (dayRect.left + left < bodyRect.left) {
        left = bodyRect.left - dayRect.left;
      } else if (dayRect.left + left + popupRect.width > bodyRect.right) {
        left = bodyRect.right - dayRect.left - popupRect.width;
      }
    }
    $$invalidate(2, style += `left:${left}px;`);
    let top;
    if (popupRect.height >= bodyRect.height) {
      top = bodyRect.top - dayRect.top;
      let bottom = dayRect.bottom - bodyRect.bottom;
      $$invalidate(2, style += `bottom:${bottom}px;`);
    } else {
      top = (dayRect.height - popupRect.height) / 2;
      if (dayRect.top + top < bodyRect.top) {
        top = bodyRect.top - dayRect.top;
      } else if (dayRect.top + top + popupRect.height > bodyRect.bottom) {
        top = bodyRect.bottom - dayRect.top - popupRect.height;
      }
    }
    $$invalidate(2, style += `top:${top}px;`);
  }
  function reposition() {
    if (el) {
      $$invalidate(2, style = "");
      tick().then(() => {
        if ($_popupChunks.length) {
          position();
        } else {
          close();
        }
      });
    }
  }
  function close(e) {
    set_store_value(_popupDate, $_popupDate = null, $_popupDate);
  }
  function handlePointerDownOutside(e) {
    close();
    $_interaction.action?.noClick();
  }
  function pointerdown_handler(event) {
    bubble.call(this, $$self, event);
  }
  function div2_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(1, el);
    });
  }
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*$_popupChunks*/
    1) {
      if ($_popupChunks) {
        reposition();
      }
    }
  };
  return [
    $_popupChunks,
    el,
    style,
    $_popupDate,
    $theme,
    $_intlDayPopover,
    $buttonText,
    buttonText,
    theme,
    _interaction,
    _intlDayPopover,
    _popupDate,
    _popupChunks,
    close,
    handlePointerDownOutside,
    pointerdown_handler,
    div2_binding
  ];
}
var Popup = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$32, create_fragment$32, safe_not_equal, {});
  }
};
function get_each_context$22(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[42] = list[i];
  child_ctx[43] = list;
  child_ctx[44] = i;
  return child_ctx;
}
function create_if_block_32(ctx) {
  let div;
  let event;
  let div_class_value;
  let current;
  event = new Event({ props: { chunk: (
    /*iChunks*/
    ctx[2][1]
  ) } });
  return {
    c() {
      div = element("div");
      create_component(event.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[12].events);
    },
    m(target, anchor) {
      insert(target, div, anchor);
      mount_component(event, div, null);
      current = true;
    },
    p(ctx2, dirty) {
      const event_changes = {};
      if (dirty[0] & /*iChunks*/
      4) event_changes.chunk = /*iChunks*/
      ctx2[2][1];
      event.$set(event_changes);
      if (!current || dirty[0] & /*$theme*/
      4096 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[12].events)) {
        attr(div, "class", div_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      destroy_component(event);
    }
  };
}
function create_if_block_22(ctx) {
  let div;
  let event;
  let div_class_value;
  let current;
  event = new Event({ props: { chunk: (
    /*iChunks*/
    ctx[2][0]
  ) } });
  return {
    c() {
      div = element("div");
      create_component(event.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[12].events + " " + /*$theme*/
      ctx[12].preview);
    },
    m(target, anchor) {
      insert(target, div, anchor);
      mount_component(event, div, null);
      current = true;
    },
    p(ctx2, dirty) {
      const event_changes = {};
      if (dirty[0] & /*iChunks*/
      4) event_changes.chunk = /*iChunks*/
      ctx2[2][0];
      event.$set(event_changes);
      if (!current || dirty[0] & /*$theme*/
      4096 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[12].events + " " + /*$theme*/
      ctx2[12].preview)) {
        attr(div, "class", div_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      destroy_component(event);
    }
  };
}
function create_each_block$22(key_1, ctx) {
  let first;
  let event;
  let i = (
    /*i*/
    ctx[44]
  );
  let current;
  const assign_event = () => (
    /*event_binding*/
    ctx[36](event, i)
  );
  const unassign_event = () => (
    /*event_binding*/
    ctx[36](null, i)
  );
  let event_props = {
    chunk: (
      /*chunk*/
      ctx[42]
    ),
    longChunks: (
      /*longChunks*/
      ctx[1]
    )
  };
  event = new Event({ props: event_props });
  assign_event();
  return {
    key: key_1,
    first: null,
    c() {
      first = empty();
      create_component(event.$$.fragment);
      this.first = first;
    },
    m(target, anchor) {
      insert(target, first, anchor);
      mount_component(event, target, anchor);
      current = true;
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (i !== /*i*/
      ctx[44]) {
        unassign_event();
        i = /*i*/
        ctx[44];
        assign_event();
      }
      const event_changes = {};
      if (dirty[0] & /*dayChunks*/
      16) event_changes.chunk = /*chunk*/
      ctx[42];
      if (dirty[0] & /*longChunks*/
      2) event_changes.longChunks = /*longChunks*/
      ctx[1];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(first);
      }
      unassign_event();
      destroy_component(event, detaching);
    }
  };
}
function create_if_block_12(ctx) {
  let popup;
  let current;
  popup = new Popup({});
  return {
    c() {
      create_component(popup.$$.fragment);
    },
    m(target, anchor) {
      mount_component(popup, target, anchor);
      current = true;
    },
    i(local) {
      if (current) return;
      transition_in(popup.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(popup.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(popup, detaching);
    }
  };
}
function create_if_block2(ctx) {
  let a;
  let setContent_action;
  let mounted;
  let dispose;
  return {
    c() {
      a = element("a");
      attr(a, "role", "button");
      attr(a, "tabindex", "0");
      attr(a, "aria-haspopup", "true");
    },
    m(target, anchor) {
      insert(target, a, anchor);
      if (!mounted) {
        dispose = [
          listen(a, "click", stop_propagation(
            /*showMore*/
            ctx[26]
          )),
          listen(a, "keydown", keyEnter(
            /*showMore*/
            ctx[26]
          )),
          listen(a, "pointerdown", stop_propagation(
            /*pointerdown_handler*/
            ctx[35]
          )),
          action_destroyer(setContent_action = setContent.call(
            null,
            a,
            /*moreLink*/
            ctx[10]
          ))
        ];
        mounted = true;
      }
    },
    p(ctx2, dirty) {
      if (setContent_action && is_function(setContent_action.update) && dirty[0] & /*moreLink*/
      1024) setContent_action.update.call(
        null,
        /*moreLink*/
        ctx2[10]
      );
    },
    d(detaching) {
      if (detaching) {
        detach(a);
      }
      mounted = false;
      run_all(dispose);
    }
  };
}
function create_fragment$22(ctx) {
  let div2;
  let time;
  let time_class_value;
  let time_datetime_value;
  let setContent_action;
  let t0;
  let show_if_1 = (
    /*iChunks*/
    ctx[2][1] && datesEqual(
      /*iChunks*/
      ctx[2][1].date,
      /*date*/
      ctx[0]
    )
  );
  let t1;
  let show_if = (
    /*iChunks*/
    ctx[2][0] && datesEqual(
      /*iChunks*/
      ctx[2][0].date,
      /*date*/
      ctx[0]
    )
  );
  let t2;
  let div0;
  let each_blocks = [];
  let each_1_lookup = /* @__PURE__ */ new Map();
  let div0_class_value;
  let t3;
  let t4;
  let div1;
  let div1_class_value;
  let div2_class_value;
  let current;
  let mounted;
  let dispose;
  let if_block0 = show_if_1 && create_if_block_32(ctx);
  let if_block1 = show_if && create_if_block_22(ctx);
  let each_value = ensure_array_like(
    /*dayChunks*/
    ctx[4]
  );
  const get_key = (ctx2) => (
    /*chunk*/
    ctx2[42].event
  );
  for (let i = 0; i < each_value.length; i += 1) {
    let child_ctx = get_each_context$22(ctx, each_value, i);
    let key = get_key(child_ctx);
    each_1_lookup.set(key, each_blocks[i] = create_each_block$22(key, child_ctx));
  }
  let if_block2 = (
    /*showPopup*/
    ctx[6] && create_if_block_12()
  );
  let if_block3 = (
    /*hiddenEvents*/
    ctx[5].size && create_if_block2(ctx)
  );
  return {
    c() {
      div2 = element("div");
      time = element("time");
      t0 = space();
      if (if_block0) if_block0.c();
      t1 = space();
      if (if_block1) if_block1.c();
      t2 = space();
      div0 = element("div");
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      t3 = space();
      if (if_block2) if_block2.c();
      t4 = space();
      div1 = element("div");
      if (if_block3) if_block3.c();
      attr(time, "class", time_class_value = /*$theme*/
      ctx[12].dayHead);
      attr(time, "datetime", time_datetime_value = toISOString(
        /*date*/
        ctx[0],
        10
      ));
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[12].events);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[12].dayFoot);
      attr(div2, "class", div2_class_value = /*$theme*/
      ctx[12].day + " " + /*$theme*/
      ctx[12].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[7] ? " " + /*$theme*/
      ctx[12].today : "") + /*otherMonth*/
      (ctx[8] ? " " + /*$theme*/
      ctx[12].otherMonth : "") + /*highlight*/
      (ctx[9] ? " " + /*$theme*/
      ctx[12].highlight : ""));
      attr(div2, "role", "cell");
    },
    m(target, anchor) {
      insert(target, div2, anchor);
      append(div2, time);
      append(div2, t0);
      if (if_block0) if_block0.m(div2, null);
      append(div2, t1);
      if (if_block1) if_block1.m(div2, null);
      append(div2, t2);
      append(div2, div0);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div0, null);
        }
      }
      append(div2, t3);
      if (if_block2) if_block2.m(div2, null);
      append(div2, t4);
      append(div2, div1);
      if (if_block3) if_block3.m(div1, null);
      ctx[37](div2);
      current = true;
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            time,
            /*$_intlDayCell*/
            ctx[14].format(
              /*date*/
              ctx[0]
            )
          )),
          listen(div2, "pointerenter", function() {
            if (is_function(
              /*createPointerEnterHandler*/
              ctx[25](
                /*$_interaction*/
                ctx[13]
              )
            )) ctx[25](
              /*$_interaction*/
              ctx[13]
            ).apply(this, arguments);
          }),
          listen(div2, "pointerleave", function() {
            if (is_function(
              /*$_interaction*/
              ctx[13].pointer?.leave
            )) ctx[13].pointer?.leave.apply(this, arguments);
          }),
          listen(div2, "pointerdown", function() {
            if (is_function(
              /*$_interaction*/
              ctx[13].action?.select
            )) ctx[13].action?.select.apply(this, arguments);
          })
        ];
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (!current || dirty[0] & /*$theme*/
      4096 && time_class_value !== (time_class_value = /*$theme*/
      ctx[12].dayHead)) {
        attr(time, "class", time_class_value);
      }
      if (!current || dirty[0] & /*date*/
      1 && time_datetime_value !== (time_datetime_value = toISOString(
        /*date*/
        ctx[0],
        10
      ))) {
        attr(time, "datetime", time_datetime_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty[0] & /*$_intlDayCell, date*/
      16385) setContent_action.update.call(
        null,
        /*$_intlDayCell*/
        ctx[14].format(
          /*date*/
          ctx[0]
        )
      );
      if (dirty[0] & /*iChunks, date*/
      5) show_if_1 = /*iChunks*/
      ctx[2][1] && datesEqual(
        /*iChunks*/
        ctx[2][1].date,
        /*date*/
        ctx[0]
      );
      if (show_if_1) {
        if (if_block0) {
          if_block0.p(ctx, dirty);
          if (dirty[0] & /*iChunks, date*/
          5) {
            transition_in(if_block0, 1);
          }
        } else {
          if_block0 = create_if_block_32(ctx);
          if_block0.c();
          transition_in(if_block0, 1);
          if_block0.m(div2, t1);
        }
      } else if (if_block0) {
        group_outros();
        transition_out(if_block0, 1, 1, () => {
          if_block0 = null;
        });
        check_outros();
      }
      if (dirty[0] & /*iChunks, date*/
      5) show_if = /*iChunks*/
      ctx[2][0] && datesEqual(
        /*iChunks*/
        ctx[2][0].date,
        /*date*/
        ctx[0]
      );
      if (show_if) {
        if (if_block1) {
          if_block1.p(ctx, dirty);
          if (dirty[0] & /*iChunks, date*/
          5) {
            transition_in(if_block1, 1);
          }
        } else {
          if_block1 = create_if_block_22(ctx);
          if_block1.c();
          transition_in(if_block1, 1);
          if_block1.m(div2, t2);
        }
      } else if (if_block1) {
        group_outros();
        transition_out(if_block1, 1, 1, () => {
          if_block1 = null;
        });
        check_outros();
      }
      if (dirty[0] & /*dayChunks, longChunks, refs*/
      2066) {
        each_value = ensure_array_like(
          /*dayChunks*/
          ctx[4]
        );
        group_outros();
        each_blocks = update_keyed_each(each_blocks, dirty, get_key, 1, ctx, each_value, each_1_lookup, div0, outro_and_destroy_block, create_each_block$22, null, get_each_context$22);
        check_outros();
      }
      if (!current || dirty[0] & /*$theme*/
      4096 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx[12].events)) {
        attr(div0, "class", div0_class_value);
      }
      if (
        /*showPopup*/
        ctx[6]
      ) {
        if (if_block2) {
          if (dirty[0] & /*showPopup*/
          64) {
            transition_in(if_block2, 1);
          }
        } else {
          if_block2 = create_if_block_12();
          if_block2.c();
          transition_in(if_block2, 1);
          if_block2.m(div2, t4);
        }
      } else if (if_block2) {
        group_outros();
        transition_out(if_block2, 1, 1, () => {
          if_block2 = null;
        });
        check_outros();
      }
      if (
        /*hiddenEvents*/
        ctx[5].size
      ) {
        if (if_block3) {
          if_block3.p(ctx, dirty);
        } else {
          if_block3 = create_if_block2(ctx);
          if_block3.c();
          if_block3.m(div1, null);
        }
      } else if (if_block3) {
        if_block3.d(1);
        if_block3 = null;
      }
      if (!current || dirty[0] & /*$theme*/
      4096 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx[12].dayFoot)) {
        attr(div1, "class", div1_class_value);
      }
      if (!current || dirty[0] & /*$theme, date, isToday, otherMonth, highlight*/
      4993 && div2_class_value !== (div2_class_value = /*$theme*/
      ctx[12].day + " " + /*$theme*/
      ctx[12].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[7] ? " " + /*$theme*/
      ctx[12].today : "") + /*otherMonth*/
      (ctx[8] ? " " + /*$theme*/
      ctx[12].otherMonth : "") + /*highlight*/
      (ctx[9] ? " " + /*$theme*/
      ctx[12].highlight : ""))) {
        attr(div2, "class", div2_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(if_block0);
      transition_in(if_block1);
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      transition_in(if_block2);
      current = true;
    },
    o(local) {
      transition_out(if_block0);
      transition_out(if_block1);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      transition_out(if_block2);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div2);
      }
      if (if_block0) if_block0.d();
      if (if_block1) if_block1.d();
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].d();
      }
      if (if_block2) if_block2.d();
      if (if_block3) if_block3.d();
      ctx[37](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function instance$22($$self, $$props, $$invalidate) {
  let $_popupChunks;
  let $_popupDate;
  let $moreLinkContent;
  let $_hiddenEvents;
  let $highlightedDates;
  let $currentDate;
  let $_today;
  let $theme;
  let $_interaction;
  let $_intlDayCell;
  let { date } = $$props;
  let { chunks } = $$props;
  let { longChunks } = $$props;
  let { iChunks = [] } = $$props;
  let { date: currentDate, dayMaxEvents, highlightedDates, moreLinkContent, theme, _hiddenEvents, _intlDayCell, _popupDate, _popupChunks, _today, _interaction, _queue } = getContext("state");
  component_subscribe($$self, currentDate, (value) => $$invalidate(33, $currentDate = value));
  component_subscribe($$self, highlightedDates, (value) => $$invalidate(32, $highlightedDates = value));
  component_subscribe($$self, moreLinkContent, (value) => $$invalidate(30, $moreLinkContent = value));
  component_subscribe($$self, theme, (value) => $$invalidate(12, $theme = value));
  component_subscribe($$self, _hiddenEvents, (value) => $$invalidate(31, $_hiddenEvents = value));
  component_subscribe($$self, _intlDayCell, (value) => $$invalidate(14, $_intlDayCell = value));
  component_subscribe($$self, _popupDate, (value) => $$invalidate(29, $_popupDate = value));
  component_subscribe($$self, _popupChunks, (value) => $$invalidate(38, $_popupChunks = value));
  component_subscribe($$self, _today, (value) => $$invalidate(34, $_today = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(13, $_interaction = value));
  let el;
  let dayChunks;
  let isToday;
  let otherMonth;
  let highlight;
  let hiddenEvents = /* @__PURE__ */ new Set();
  let moreLink = "";
  let showPopup;
  let refs = [];
  function createPointerEnterHandler(interaction) {
    return interaction.pointer ? (jsEvent) => interaction.pointer.enterDayGrid(date, jsEvent) : void 0;
  }
  function showMore() {
    set_store_value(_popupDate, $_popupDate = date, $_popupDate);
  }
  function setPopupChunks() {
    let nextDay = addDay(cloneDate(date));
    let chunks2 = dayChunks.concat(longChunks[date.getTime()]?.chunks || []);
    set_store_value(_popupChunks, $_popupChunks = chunks2.map((chunk) => assign2({}, chunk, createEventChunk(chunk.event, date, nextDay), { days: 1, dates: [date] })).sort((a, b) => a.top - b.top), $_popupChunks);
  }
  function reposition() {
    runReposition(refs, dayChunks);
  }
  function pointerdown_handler(event) {
    bubble.call(this, $$self, event);
  }
  function event_binding($$value, i) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      refs[i] = $$value;
      $$invalidate(11, refs);
    });
  }
  function div2_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(3, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("date" in $$props2) $$invalidate(0, date = $$props2.date);
    if ("chunks" in $$props2) $$invalidate(27, chunks = $$props2.chunks);
    if ("longChunks" in $$props2) $$invalidate(1, longChunks = $$props2.longChunks);
    if ("iChunks" in $$props2) $$invalidate(2, iChunks = $$props2.iChunks);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty[0] & /*hiddenEvents, chunks, date, dayChunks*/
    134217777) {
      {
        $$invalidate(4, dayChunks = []);
        hiddenEvents.clear();
        $$invalidate(5, hiddenEvents), $$invalidate(27, chunks), $$invalidate(0, date), $$invalidate(4, dayChunks);
        for (let chunk of chunks) {
          if (datesEqual(chunk.date, date)) {
            dayChunks.push(chunk);
          }
        }
      }
    }
    if ($$self.$$.dirty[0] & /*date, hiddenEvents*/
    33) {
      set_store_value(_hiddenEvents, $_hiddenEvents[date.getTime()] = hiddenEvents, $_hiddenEvents);
    }
    if ($$self.$$.dirty[0] & /*date*/
    1 | $$self.$$.dirty[1] & /*$_today*/
    8) {
      $$invalidate(7, isToday = datesEqual(date, $_today));
    }
    if ($$self.$$.dirty[0] & /*date*/
    1 | $$self.$$.dirty[1] & /*$currentDate, $highlightedDates*/
    6) {
      {
        $$invalidate(8, otherMonth = date.getUTCMonth() !== $currentDate.getUTCMonth());
        $$invalidate(9, highlight = $highlightedDates.some((d) => datesEqual(d, date)));
      }
    }
    if ($$self.$$.dirty[0] & /*hiddenEvents, $moreLinkContent*/
    1073741856 | $$self.$$.dirty[1] & /*$_hiddenEvents*/
    1) {
      if ($_hiddenEvents && hiddenEvents.size) {
        let text2 = "+" + hiddenEvents.size + " more";
        if ($moreLinkContent) {
          $$invalidate(10, moreLink = is_function($moreLinkContent) ? $moreLinkContent({ num: hiddenEvents.size, text: text2 }) : $moreLinkContent);
        } else {
          $$invalidate(10, moreLink = text2);
        }
      }
    }
    if ($$self.$$.dirty[0] & /*$_popupDate, date*/
    536870913) {
      $$invalidate(6, showPopup = $_popupDate && datesEqual(date, $_popupDate));
    }
    if ($$self.$$.dirty[0] & /*showPopup, longChunks, dayChunks*/
    82) {
      if (showPopup && longChunks && dayChunks) {
        tick().then(setPopupChunks);
      }
    }
    if ($$self.$$.dirty[0] & /*el, date*/
    9) {
      if (el) {
        setPayload(el, () => ({
          allDay: true,
          date,
          resource: void 0,
          dayEl: el
        }));
      }
    }
  };
  return [
    date,
    longChunks,
    iChunks,
    el,
    dayChunks,
    hiddenEvents,
    showPopup,
    isToday,
    otherMonth,
    highlight,
    moreLink,
    refs,
    $theme,
    $_interaction,
    $_intlDayCell,
    currentDate,
    highlightedDates,
    moreLinkContent,
    theme,
    _hiddenEvents,
    _intlDayCell,
    _popupDate,
    _popupChunks,
    _today,
    _interaction,
    createPointerEnterHandler,
    showMore,
    chunks,
    reposition,
    $_popupDate,
    $moreLinkContent,
    $_hiddenEvents,
    $highlightedDates,
    $currentDate,
    $_today,
    pointerdown_handler,
    event_binding,
    div2_binding
  ];
}
var Day = class extends SvelteComponent {
  constructor(options) {
    super();
    init(
      this,
      options,
      instance$22,
      create_fragment$22,
      safe_not_equal,
      {
        date: 0,
        chunks: 27,
        longChunks: 1,
        iChunks: 2,
        reposition: 28
      },
      null,
      [-1, -1]
    );
  }
  get reposition() {
    return this.$$.ctx[28];
  }
};
function get_each_context$12(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[21] = list[i];
  child_ctx[22] = list;
  child_ctx[23] = i;
  return child_ctx;
}
function create_each_block$12(ctx) {
  let day;
  let i = (
    /*i*/
    ctx[23]
  );
  let current;
  const assign_day = () => (
    /*day_binding*/
    ctx[18](day, i)
  );
  const unassign_day = () => (
    /*day_binding*/
    ctx[18](null, i)
  );
  let day_props = {
    date: (
      /*date*/
      ctx[21]
    ),
    chunks: (
      /*chunks*/
      ctx[1]
    ),
    longChunks: (
      /*longChunks*/
      ctx[2]
    ),
    iChunks: (
      /*iChunks*/
      ctx[3]
    )
  };
  day = new Day({ props: day_props });
  assign_day();
  return {
    c() {
      create_component(day.$$.fragment);
    },
    m(target, anchor) {
      mount_component(day, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      if (i !== /*i*/
      ctx2[23]) {
        unassign_day();
        i = /*i*/
        ctx2[23];
        assign_day();
      }
      const day_changes = {};
      if (dirty & /*dates*/
      1) day_changes.date = /*date*/
      ctx2[21];
      if (dirty & /*chunks*/
      2) day_changes.chunks = /*chunks*/
      ctx2[1];
      if (dirty & /*longChunks*/
      4) day_changes.longChunks = /*longChunks*/
      ctx2[2];
      if (dirty & /*iChunks*/
      8) day_changes.iChunks = /*iChunks*/
      ctx2[3];
      day.$set(day_changes);
    },
    i(local) {
      if (current) return;
      transition_in(day.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(day.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      unassign_day();
      destroy_component(day, detaching);
    }
  };
}
function create_fragment$12(ctx) {
  let div;
  let div_class_value;
  let current;
  let mounted;
  let dispose;
  let each_value = ensure_array_like(
    /*dates*/
    ctx[0]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block$12(get_each_context$12(ctx, each_value, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      div = element("div");
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      attr(div, "class", div_class_value = /*$theme*/
      ctx[5].days);
      attr(div, "role", "row");
    },
    m(target, anchor) {
      insert(target, div, anchor);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div, null);
        }
      }
      current = true;
      if (!mounted) {
        dispose = listen(
          window,
          "resize",
          /*reposition*/
          ctx[11]
        );
        mounted = true;
      }
    },
    p(ctx2, [dirty]) {
      if (dirty & /*dates, chunks, longChunks, iChunks, refs*/
      31) {
        each_value = ensure_array_like(
          /*dates*/
          ctx2[0]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context$12(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block$12(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(div, null);
          }
        }
        group_outros();
        for (i = each_value.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
      if (!current || dirty & /*$theme*/
      32 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[5].days)) {
        attr(div, "class", div_class_value);
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      destroy_each(each_blocks, detaching);
      mounted = false;
      dispose();
    }
  };
}
function instance$12($$self, $$props, $$invalidate) {
  let $_hiddenEvents;
  let $hiddenDays;
  let $_iEvents;
  let $_events;
  let $theme;
  let { dates } = $$props;
  let { _events, _iEvents, _queue2, _hiddenEvents, hiddenDays, theme } = getContext("state");
  component_subscribe($$self, _events, (value) => $$invalidate(17, $_events = value));
  component_subscribe($$self, _iEvents, (value) => $$invalidate(16, $_iEvents = value));
  component_subscribe($$self, _hiddenEvents, (value) => $$invalidate(14, $_hiddenEvents = value));
  component_subscribe($$self, hiddenDays, (value) => $$invalidate(15, $hiddenDays = value));
  component_subscribe($$self, theme, (value) => $$invalidate(5, $theme = value));
  let chunks, longChunks, iChunks = [];
  let start;
  let end;
  let refs = [];
  let debounceHandle = {};
  function reposition() {
    debounce(() => runReposition(refs, dates), debounceHandle, _queue2);
  }
  function day_binding($$value, i) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      refs[i] = $$value;
      $$invalidate(4, refs);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("dates" in $$props2) $$invalidate(0, dates = $$props2.dates);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*dates*/
    1) {
      {
        $$invalidate(12, start = dates[0]);
        $$invalidate(13, end = addDay(cloneDate(dates[dates.length - 1])));
      }
    }
    if ($$self.$$.dirty & /*$_events, start, end, chunks, $hiddenDays*/
    176130) {
      {
        $$invalidate(1, chunks = []);
        for (let event of $_events) {
          if (!bgEvent(event.display) && eventIntersects(event, start, end)) {
            let chunk = createEventChunk(event, start, end);
            chunks.push(chunk);
          }
        }
        $$invalidate(2, longChunks = prepareEventChunks(chunks, $hiddenDays));
        reposition();
      }
    }
    if ($$self.$$.dirty & /*$_iEvents, start, end, $hiddenDays*/
    110592) {
      $$invalidate(3, iChunks = $_iEvents.map((event) => {
        let chunk;
        if (event && eventIntersects(event, start, end)) {
          chunk = createEventChunk(event, start, end);
          prepareEventChunks([chunk], $hiddenDays);
        } else {
          chunk = null;
        }
        return chunk;
      }));
    }
    if ($$self.$$.dirty & /*$_hiddenEvents*/
    16384) {
      if ($_hiddenEvents) {
        tick().then(reposition);
      }
    }
  };
  return [
    dates,
    chunks,
    longChunks,
    iChunks,
    refs,
    $theme,
    _events,
    _iEvents,
    _hiddenEvents,
    hiddenDays,
    theme,
    reposition,
    start,
    end,
    $_hiddenEvents,
    $hiddenDays,
    $_iEvents,
    $_events,
    day_binding
  ];
}
var Week = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$12, create_fragment$12, safe_not_equal, { dates: 0 });
  }
};
function get_each_context2(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[10] = list[i];
  return child_ctx;
}
function create_each_block2(ctx) {
  let week;
  let current;
  week = new Week({ props: { dates: (
    /*dates*/
    ctx[10]
  ) } });
  return {
    c() {
      create_component(week.$$.fragment);
    },
    m(target, anchor) {
      mount_component(week, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      const week_changes = {};
      if (dirty & /*weeks*/
      1) week_changes.dates = /*dates*/
      ctx2[10];
      week.$set(week_changes);
    },
    i(local) {
      if (current) return;
      transition_in(week.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(week.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(week, detaching);
    }
  };
}
function create_default_slot(ctx) {
  let each_1_anchor;
  let current;
  let each_value = ensure_array_like(
    /*weeks*/
    ctx[0]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block2(get_each_context2(ctx, each_value, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      if (dirty & /*weeks*/
      1) {
        each_value = ensure_array_like(
          /*weeks*/
          ctx2[0]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context2(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block2(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        group_outros();
        for (i = each_value.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function create_fragment2(ctx) {
  let header;
  let t;
  let body;
  let current;
  header = new Header({});
  body = new Body({
    props: {
      $$slots: { default: [create_default_slot] },
      $$scope: { ctx }
    }
  });
  return {
    c() {
      create_component(header.$$.fragment);
      t = space();
      create_component(body.$$.fragment);
    },
    m(target, anchor) {
      mount_component(header, target, anchor);
      insert(target, t, anchor);
      mount_component(body, target, anchor);
      current = true;
    },
    p(ctx2, [dirty]) {
      const body_changes = {};
      if (dirty & /*$$scope, weeks*/
      8193) {
        body_changes.$$scope = { dirty, ctx: ctx2 };
      }
      body.$set(body_changes);
    },
    i(local) {
      if (current) return;
      transition_in(header.$$.fragment, local);
      transition_in(body.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(header.$$.fragment, local);
      transition_out(body.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(t);
      }
      destroy_component(header, detaching);
      destroy_component(body, detaching);
    }
  };
}
function instance2($$self, $$props, $$invalidate) {
  let $_viewDates;
  let $dayMaxEvents;
  let $_hiddenEvents;
  let $hiddenDays;
  let { _viewDates, _hiddenEvents, dayMaxEvents, hiddenDays } = getContext("state");
  component_subscribe($$self, _viewDates, (value) => $$invalidate(6, $_viewDates = value));
  component_subscribe($$self, _hiddenEvents, (value) => $$invalidate(9, $_hiddenEvents = value));
  component_subscribe($$self, dayMaxEvents, (value) => $$invalidate(7, $dayMaxEvents = value));
  component_subscribe($$self, hiddenDays, (value) => $$invalidate(8, $hiddenDays = value));
  let weeks;
  let days2;
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*$hiddenDays, $dayMaxEvents, $_viewDates, days, weeks*/
    481) {
      {
        $$invalidate(0, weeks = []);
        $$invalidate(5, days2 = 7 - $hiddenDays.length);
        set_store_value(_hiddenEvents, $_hiddenEvents = {}, $_hiddenEvents);
        for (let i = 0; i < $_viewDates.length / days2; ++i) {
          let dates = [];
          for (let j = 0; j < days2; ++j) {
            dates.push($_viewDates[i * days2 + j]);
          }
          weeks.push(dates);
        }
      }
    }
  };
  return [
    weeks,
    _viewDates,
    _hiddenEvents,
    dayMaxEvents,
    hiddenDays,
    days2,
    $_viewDates,
    $dayMaxEvents,
    $hiddenDays
  ];
}
var View = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance2, create_fragment2, safe_not_equal, {});
  }
};
var index = {
  createOptions(options) {
    options.dayMaxEvents = false;
    options.dayCellFormat = { day: "numeric" };
    options.dayPopoverFormat = { month: "long", day: "numeric", year: "numeric" };
    options.moreLinkContent = void 0;
    options.buttonText.dayGridMonth = "month";
    options.buttonText.close = "Close";
    options.theme.uniform = "ec-uniform";
    options.theme.dayFoot = "ec-day-foot";
    options.theme.popup = "ec-popup";
    options.view = "dayGridMonth";
    options.views.dayGridMonth = {
      buttonText: btnTextMonth,
      component: View,
      dayHeaderFormat: { weekday: "short" },
      dayHeaderAriaLabelFormat: { weekday: "long" },
      displayEventEnd: false,
      duration: { months: 1 },
      theme: themeView("ec-day-grid ec-month-view"),
      titleFormat: { year: "numeric", month: "long" }
    };
  },
  createStores(state) {
    state._days = days(state);
    state._intlDayCell = intl(state.locale, state.dayCellFormat);
    state._intlDayPopover = intl(state.locale, state.dayPopoverFormat);
    state._hiddenEvents = writable({});
    state._popupDate = writable(null);
    state._popupChunks = writable([]);
  }
};

// node_modules/@event-calendar/time-grid/index.js
function times(state) {
  return derived(
    [state._slotTimeLimits, state._intlSlotLabel, state.slotDuration],
    ([$_slotTimeLimits, $_intlSlotLabel, $slotDuration]) => {
      let large = $slotDuration.seconds >= 3600;
      let times2 = [];
      let date = setMidnight(createDate());
      let end = cloneDate(date);
      let i = 1;
      addDuration(date, $_slotTimeLimits.min);
      addDuration(end, $_slotTimeLimits.max);
      while (date < end) {
        times2.push([
          toISOString(date),
          times2.length && (i || large) ? $_intlSlotLabel.format(date) : ""
        ]);
        addDuration(date, $slotDuration);
        i = 1 - i;
      }
      return times2;
    }
  );
}
function slotTimeLimits(state) {
  return derived(
    [state._events, state._viewDates, state.flexibleSlotTimeLimits, state.slotMinTime, state.slotMaxTime],
    ([$_events, $_viewDates, $flexibleSlotTimeLimits, $slotMinTime, $slotMaxTime]) => {
      let min$1 = createDuration($slotMinTime);
      let max$1 = createDuration($slotMaxTime);
      if ($flexibleSlotTimeLimits) {
        let minMin = createDuration(min(min$1.seconds, max(0, max$1.seconds - DAY_IN_SECONDS)));
        let maxMax = createDuration(max(max$1.seconds, minMin.seconds + DAY_IN_SECONDS));
        let filter = is_function($flexibleSlotTimeLimits?.eventFilter) ? $flexibleSlotTimeLimits.eventFilter : (event) => !bgEvent(event.display);
        loop: for (let date of $_viewDates) {
          let start = addDuration(cloneDate(date), min$1);
          let end = addDuration(cloneDate(date), max$1);
          let minStart = addDuration(cloneDate(date), minMin);
          let maxEnd = addDuration(cloneDate(date), maxMax);
          for (let event of $_events) {
            if (!event.allDay && filter(event) && event.start < maxEnd && event.end > minStart) {
              if (event.start < start) {
                let seconds = max((event.start - date) / 1e3, minMin.seconds);
                if (seconds < min$1.seconds) {
                  min$1.seconds = seconds;
                }
              }
              if (event.end > end) {
                let seconds = min((event.end - date) / 1e3, maxMax.seconds);
                if (seconds > max$1.seconds) {
                  max$1.seconds = seconds;
                }
              }
              if (min$1.seconds === minMin.seconds && max$1.seconds === maxMax.seconds) {
                break loop;
              }
            }
          }
        }
      }
      return { min: min$1, max: max$1 };
    }
  );
}
function groupEventChunks(chunks) {
  if (!chunks.length) {
    return;
  }
  sortEventChunks(chunks);
  let group = {
    columns: [],
    end: chunks[0].end
  };
  for (let chunk of chunks) {
    let c = 0;
    if (chunk.start < group.end) {
      for (; c < group.columns.length; ++c) {
        if (group.columns[c][group.columns[c].length - 1].end <= chunk.start) {
          break;
        }
      }
      if (chunk.end > group.end) {
        group.end = chunk.end;
      }
    } else {
      group = {
        columns: [],
        end: chunk.end
      };
    }
    if (group.columns.length < c + 1) {
      group.columns.push([]);
    }
    group.columns[c].push(chunk);
    chunk.group = group;
    chunk.column = c;
  }
}
function createAllDayContent(allDayContent) {
  let text2 = "all-day";
  let content;
  if (allDayContent) {
    content = is_function(allDayContent) ? allDayContent({ text: text2 }) : allDayContent;
    if (typeof content === "string") {
      content = { html: content };
    }
  } else {
    content = {
      html: text2
    };
  }
  return content;
}
var get_lines_slot_changes = (dirty) => ({});
var get_lines_slot_context = (ctx) => ({});
function get_each_context$5(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[9] = list[i];
  return child_ctx;
}
function create_each_block$5(ctx) {
  let time_1;
  let time_1_class_value;
  let time_1_datetime_value;
  let setContent_action;
  let mounted;
  let dispose;
  return {
    c() {
      time_1 = element("time");
      attr(time_1, "class", time_1_class_value = /*$theme*/
      ctx[1].time);
      attr(time_1, "datetime", time_1_datetime_value = /*time*/
      ctx[9][0]);
    },
    m(target, anchor) {
      insert(target, time_1, anchor);
      if (!mounted) {
        dispose = action_destroyer(setContent_action = setContent.call(
          null,
          time_1,
          /*time*/
          ctx[9][1]
        ));
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (dirty & /*$theme*/
      2 && time_1_class_value !== (time_1_class_value = /*$theme*/
      ctx[1].time)) {
        attr(time_1, "class", time_1_class_value);
      }
      if (dirty & /*$_times*/
      4 && time_1_datetime_value !== (time_1_datetime_value = /*time*/
      ctx[9][0])) {
        attr(time_1, "datetime", time_1_datetime_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*$_times*/
      4) setContent_action.update.call(
        null,
        /*time*/
        ctx[9][1]
      );
    },
    d(detaching) {
      if (detaching) {
        detach(time_1);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_fragment$8(ctx) {
  let div1;
  let div0;
  let div0_class_value;
  let setContent_action;
  let t0;
  let div1_class_value;
  let t1;
  let div3;
  let div2;
  let div2_class_value;
  let t2;
  let div3_class_value;
  let current;
  let mounted;
  let dispose;
  let each_value = ensure_array_like(
    /*$_times*/
    ctx[2]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block$5(get_each_context$5(ctx, each_value, i));
  }
  const lines_slot_template = (
    /*#slots*/
    ctx[8].lines
  );
  const lines_slot = create_slot(
    lines_slot_template,
    ctx,
    /*$$scope*/
    ctx[7],
    get_lines_slot_context
  );
  const default_slot_template = (
    /*#slots*/
    ctx[8].default
  );
  const default_slot = create_slot(
    default_slot_template,
    ctx,
    /*$$scope*/
    ctx[7],
    null
  );
  return {
    c() {
      div1 = element("div");
      div0 = element("div");
      t0 = space();
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      t1 = space();
      div3 = element("div");
      div2 = element("div");
      if (lines_slot) lines_slot.c();
      t2 = space();
      if (default_slot) default_slot.c();
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[1].sidebarTitle);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[1].sidebar);
      attr(div2, "class", div2_class_value = /*$theme*/
      ctx[1].lines);
      attr(div3, "class", div3_class_value = /*$theme*/
      ctx[1].days);
      attr(div3, "role", "row");
    },
    m(target, anchor) {
      insert(target, div1, anchor);
      append(div1, div0);
      append(div1, t0);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div1, null);
        }
      }
      insert(target, t1, anchor);
      insert(target, div3, anchor);
      append(div3, div2);
      if (lines_slot) {
        lines_slot.m(div2, null);
      }
      append(div3, t2);
      if (default_slot) {
        default_slot.m(div3, null);
      }
      current = true;
      if (!mounted) {
        dispose = action_destroyer(setContent_action = setContent.call(
          null,
          div0,
          /*allDayText*/
          ctx[0]
        ));
        mounted = true;
      }
    },
    p(ctx2, [dirty]) {
      if (!current || dirty & /*$theme*/
      2 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[1].sidebarTitle)) {
        attr(div0, "class", div0_class_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*allDayText*/
      1) setContent_action.update.call(
        null,
        /*allDayText*/
        ctx2[0]
      );
      if (dirty & /*$theme, $_times*/
      6) {
        each_value = ensure_array_like(
          /*$_times*/
          ctx2[2]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context$5(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
          } else {
            each_blocks[i] = create_each_block$5(child_ctx);
            each_blocks[i].c();
            each_blocks[i].m(div1, null);
          }
        }
        for (; i < each_blocks.length; i += 1) {
          each_blocks[i].d(1);
        }
        each_blocks.length = each_value.length;
      }
      if (!current || dirty & /*$theme*/
      2 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[1].sidebar)) {
        attr(div1, "class", div1_class_value);
      }
      if (lines_slot) {
        if (lines_slot.p && (!current || dirty & /*$$scope*/
        128)) {
          update_slot_base(
            lines_slot,
            lines_slot_template,
            ctx2,
            /*$$scope*/
            ctx2[7],
            !current ? get_all_dirty_from_scope(
              /*$$scope*/
              ctx2[7]
            ) : get_slot_changes(
              lines_slot_template,
              /*$$scope*/
              ctx2[7],
              dirty,
              get_lines_slot_changes
            ),
            get_lines_slot_context
          );
        }
      }
      if (!current || dirty & /*$theme*/
      2 && div2_class_value !== (div2_class_value = /*$theme*/
      ctx2[1].lines)) {
        attr(div2, "class", div2_class_value);
      }
      if (default_slot) {
        if (default_slot.p && (!current || dirty & /*$$scope*/
        128)) {
          update_slot_base(
            default_slot,
            default_slot_template,
            ctx2,
            /*$$scope*/
            ctx2[7],
            !current ? get_all_dirty_from_scope(
              /*$$scope*/
              ctx2[7]
            ) : get_slot_changes(
              default_slot_template,
              /*$$scope*/
              ctx2[7],
              dirty,
              null
            ),
            null
          );
        }
      }
      if (!current || dirty & /*$theme*/
      2 && div3_class_value !== (div3_class_value = /*$theme*/
      ctx2[1].days)) {
        attr(div3, "class", div3_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(lines_slot, local);
      transition_in(default_slot, local);
      current = true;
    },
    o(local) {
      transition_out(lines_slot, local);
      transition_out(default_slot, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div1);
        detach(t1);
        detach(div3);
      }
      destroy_each(each_blocks, detaching);
      if (lines_slot) lines_slot.d(detaching);
      if (default_slot) default_slot.d(detaching);
      mounted = false;
      dispose();
    }
  };
}
function instance$8($$self, $$props, $$invalidate) {
  let $allDayContent;
  let $theme;
  let $_times;
  let { $$slots: slots = {}, $$scope } = $$props;
  let { allDayContent, theme, _times } = getContext("state");
  component_subscribe($$self, allDayContent, (value) => $$invalidate(6, $allDayContent = value));
  component_subscribe($$self, theme, (value) => $$invalidate(1, $theme = value));
  component_subscribe($$self, _times, (value) => $$invalidate(2, $_times = value));
  let allDayText;
  $$self.$$set = ($$props2) => {
    if ("$$scope" in $$props2) $$invalidate(7, $$scope = $$props2.$$scope);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*$allDayContent*/
    64) {
      $$invalidate(0, allDayText = createAllDayContent($allDayContent));
    }
  };
  return [
    allDayText,
    $theme,
    $_times,
    allDayContent,
    theme,
    _times,
    $allDayContent,
    $$scope,
    slots
  ];
}
var Section = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$8, create_fragment$8, safe_not_equal, {});
  }
};
function get_each_context$42(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[23] = list[i];
  return child_ctx;
}
function create_default_slot$1(ctx) {
  let current;
  const default_slot_template = (
    /*#slots*/
    ctx[16].default
  );
  const default_slot = create_slot(
    default_slot_template,
    ctx,
    /*$$scope*/
    ctx[18],
    null
  );
  return {
    c() {
      if (default_slot) default_slot.c();
    },
    m(target, anchor) {
      if (default_slot) {
        default_slot.m(target, anchor);
      }
      current = true;
    },
    p(ctx2, dirty) {
      if (default_slot) {
        if (default_slot.p && (!current || dirty & /*$$scope*/
        262144)) {
          update_slot_base(
            default_slot,
            default_slot_template,
            ctx2,
            /*$$scope*/
            ctx2[18],
            !current ? get_all_dirty_from_scope(
              /*$$scope*/
              ctx2[18]
            ) : get_slot_changes(
              default_slot_template,
              /*$$scope*/
              ctx2[18],
              dirty,
              null
            ),
            null
          );
        }
      }
    },
    i(local) {
      if (current) return;
      transition_in(default_slot, local);
      current = true;
    },
    o(local) {
      transition_out(default_slot, local);
      current = false;
    },
    d(detaching) {
      if (default_slot) default_slot.d(detaching);
    }
  };
}
function create_each_block$42(ctx) {
  let div;
  let div_class_value;
  return {
    c() {
      div = element("div");
      attr(div, "class", div_class_value = /*$theme*/
      ctx[3].line);
    },
    m(target, anchor) {
      insert(target, div, anchor);
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme*/
      8 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[3].line)) {
        attr(div, "class", div_class_value);
      }
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
    }
  };
}
function create_lines_slot(ctx) {
  let each_1_anchor;
  let each_value = ensure_array_like(
    /*lines*/
    ctx[2]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block$42(get_each_context$42(ctx, each_value, i));
  }
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme, lines*/
      12) {
        each_value = ensure_array_like(
          /*lines*/
          ctx2[2]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context$42(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
          } else {
            each_blocks[i] = create_each_block$42(child_ctx);
            each_blocks[i].c();
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        for (; i < each_blocks.length; i += 1) {
          each_blocks[i].d(1);
        }
        each_blocks.length = each_value.length;
      }
    },
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function create_fragment$7(ctx) {
  let div1;
  let div0;
  let section;
  let div0_class_value;
  let div1_class_value;
  let current;
  section = new Section({
    props: {
      $$slots: {
        lines: [create_lines_slot],
        default: [create_default_slot$1]
      },
      $$scope: { ctx }
    }
  });
  return {
    c() {
      div1 = element("div");
      div0 = element("div");
      create_component(section.$$.fragment);
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[3].content);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[3].body + /*compact*/
      (ctx[1] ? " " + /*$theme*/
      ctx[3].compact : ""));
    },
    m(target, anchor) {
      insert(target, div1, anchor);
      append(div1, div0);
      mount_component(section, div0, null);
      ctx[17](div1);
      current = true;
    },
    p(ctx2, [dirty]) {
      const section_changes = {};
      if (dirty & /*$$scope, lines, $theme*/
      262156) {
        section_changes.$$scope = { dirty, ctx: ctx2 };
      }
      section.$set(section_changes);
      if (!current || dirty & /*$theme*/
      8 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[3].content)) {
        attr(div0, "class", div0_class_value);
      }
      if (!current || dirty & /*$theme, compact*/
      10 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[3].body + /*compact*/
      (ctx2[1] ? " " + /*$theme*/
      ctx2[3].compact : ""))) {
        attr(div1, "class", div1_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(section.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(section.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div1);
      }
      destroy_component(section);
      ctx[17](null);
    }
  };
}
function instance$7($$self, $$props, $$invalidate) {
  let $slotHeight;
  let $slotDuration;
  let $_slotTimeLimits;
  let $scrollTime;
  let $_viewDates;
  let $_times;
  let $_bodyEl;
  let $theme;
  let { $$slots: slots = {}, $$scope } = $$props;
  let { _bodyEl, _viewDates, _slotTimeLimits, _times, scrollTime, slotDuration, slotHeight, theme } = getContext("state");
  component_subscribe($$self, _bodyEl, (value) => $$invalidate(21, $_bodyEl = value));
  component_subscribe($$self, _viewDates, (value) => $$invalidate(14, $_viewDates = value));
  component_subscribe($$self, _slotTimeLimits, (value) => $$invalidate(20, $_slotTimeLimits = value));
  component_subscribe($$self, _times, (value) => $$invalidate(15, $_times = value));
  component_subscribe($$self, scrollTime, (value) => $$invalidate(13, $scrollTime = value));
  component_subscribe($$self, slotDuration, (value) => $$invalidate(12, $slotDuration = value));
  component_subscribe($$self, slotHeight, (value) => $$invalidate(19, $slotHeight = value));
  component_subscribe($$self, theme, (value) => $$invalidate(3, $theme = value));
  let el;
  let compact;
  let lines = [];
  function scrollToTime() {
    $$invalidate(0, el.scrollTop = (($scrollTime.seconds - $_slotTimeLimits.min.seconds) / $slotDuration.seconds - 0.5) * $slotHeight, el);
  }
  function div1_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(0, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("$$scope" in $$props2) $$invalidate(18, $$scope = $$props2.$$scope);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*el*/
    1) {
      set_store_value(_bodyEl, $_bodyEl = el, $_bodyEl);
    }
    if ($$self.$$.dirty & /*$slotDuration, $_times*/
    36864) {
      {
        $$invalidate(1, compact = $slotDuration.seconds >= 3600);
        $$invalidate(2, lines.length = $_times.length, lines);
      }
    }
    if ($$self.$$.dirty & /*el, $_viewDates, $scrollTime*/
    24577) {
      if (el) {
        scrollToTime();
      }
    }
  };
  return [
    el,
    compact,
    lines,
    $theme,
    _bodyEl,
    _viewDates,
    _slotTimeLimits,
    _times,
    scrollTime,
    slotDuration,
    slotHeight,
    theme,
    $slotDuration,
    $scrollTime,
    $_viewDates,
    $_times,
    slots,
    div1_binding,
    $$scope
  ];
}
var Body2 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$7, create_fragment$7, safe_not_equal, {});
  }
};
function create_fragment$62(ctx) {
  let article;
  let div;
  let div_class_value;
  let setContent_action;
  let t;
  let switch_instance;
  let article_role_value;
  let article_tabindex_value;
  let current;
  let mounted;
  let dispose;
  var switch_value = (
    /*$_interaction*/
    ctx[10].resizer
  );
  function switch_props(ctx2, dirty) {
    return { props: { event: (
      /*event*/
      ctx2[0]
    ) } };
  }
  if (switch_value) {
    switch_instance = construct_svelte_component(switch_value, switch_props(ctx));
    switch_instance.$on("pointerdown", function() {
      if (is_function(
        /*createDragHandler*/
        ctx[34](
          /*$_interaction*/
          ctx[10],
          true
        )
      )) ctx[34](
        /*$_interaction*/
        ctx[10],
        true
      ).apply(this, arguments);
    });
  }
  return {
    c() {
      article = element("article");
      div = element("div");
      t = space();
      if (switch_instance) create_component(switch_instance.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[2].eventBody);
      attr(
        article,
        "class",
        /*classes*/
        ctx[4]
      );
      attr(
        article,
        "style",
        /*style*/
        ctx[5]
      );
      attr(article, "role", article_role_value = /*onclick*/
      ctx[7] ? "button" : void 0);
      attr(article, "tabindex", article_tabindex_value = /*onclick*/
      ctx[7] ? 0 : void 0);
    },
    m(target, anchor) {
      insert(target, article, anchor);
      append(article, div);
      append(article, t);
      if (switch_instance) mount_component(switch_instance, article, null);
      ctx[53](article);
      current = true;
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            div,
            /*content*/
            ctx[6]
          )),
          listen(article, "click", function() {
            if (is_function(
              /*onclick*/
              ctx[7]
            )) ctx[7].apply(this, arguments);
          }),
          listen(article, "keydown", function() {
            if (is_function(
              /*onclick*/
              ctx[7] && keyEnter(
                /*onclick*/
                ctx[7]
              )
            )) /*onclick*/
            (ctx[7] && keyEnter(
              /*onclick*/
              ctx[7]
            )).apply(this, arguments);
          }),
          listen(article, "mouseenter", function() {
            if (is_function(
              /*createHandler*/
              ctx[33](
                /*$eventMouseEnter*/
                ctx[8],
                /*display*/
                ctx[1]
              )
            )) ctx[33](
              /*$eventMouseEnter*/
              ctx[8],
              /*display*/
              ctx[1]
            ).apply(this, arguments);
          }),
          listen(article, "mouseleave", function() {
            if (is_function(
              /*createHandler*/
              ctx[33](
                /*$eventMouseLeave*/
                ctx[9],
                /*display*/
                ctx[1]
              )
            )) ctx[33](
              /*$eventMouseLeave*/
              ctx[9],
              /*display*/
              ctx[1]
            ).apply(this, arguments);
          }),
          listen(article, "pointerdown", function() {
            if (is_function(!bgEvent(
              /*display*/
              ctx[1]
            ) && !helperEvent(
              /*display*/
              ctx[1]
            ) && /*createDragHandler*/
            ctx[34](
              /*$_interaction*/
              ctx[10]
            ))) (!bgEvent(
              /*display*/
              ctx[1]
            ) && !helperEvent(
              /*display*/
              ctx[1]
            ) && /*createDragHandler*/
            ctx[34](
              /*$_interaction*/
              ctx[10]
            )).apply(this, arguments);
          })
        ];
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (!current || dirty[0] & /*$theme*/
      4 && div_class_value !== (div_class_value = /*$theme*/
      ctx[2].eventBody)) {
        attr(div, "class", div_class_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty[0] & /*content*/
      64) setContent_action.update.call(
        null,
        /*content*/
        ctx[6]
      );
      if (dirty[0] & /*$_interaction*/
      1024 && switch_value !== (switch_value = /*$_interaction*/
      ctx[10].resizer)) {
        if (switch_instance) {
          group_outros();
          const old_component = switch_instance;
          transition_out(old_component.$$.fragment, 1, 0, () => {
            destroy_component(old_component, 1);
          });
          check_outros();
        }
        if (switch_value) {
          switch_instance = construct_svelte_component(switch_value, switch_props(ctx));
          switch_instance.$on("pointerdown", function() {
            if (is_function(
              /*createDragHandler*/
              ctx[34](
                /*$_interaction*/
                ctx[10],
                true
              )
            )) ctx[34](
              /*$_interaction*/
              ctx[10],
              true
            ).apply(this, arguments);
          });
          create_component(switch_instance.$$.fragment);
          transition_in(switch_instance.$$.fragment, 1);
          mount_component(switch_instance, article, null);
        } else {
          switch_instance = null;
        }
      } else if (switch_value) {
        const switch_instance_changes = {};
        if (dirty[0] & /*event*/
        1) switch_instance_changes.event = /*event*/
        ctx[0];
        switch_instance.$set(switch_instance_changes);
      }
      if (!current || dirty[0] & /*classes*/
      16) {
        attr(
          article,
          "class",
          /*classes*/
          ctx[4]
        );
      }
      if (!current || dirty[0] & /*style*/
      32) {
        attr(
          article,
          "style",
          /*style*/
          ctx[5]
        );
      }
      if (!current || dirty[0] & /*onclick*/
      128 && article_role_value !== (article_role_value = /*onclick*/
      ctx[7] ? "button" : void 0)) {
        attr(article, "role", article_role_value);
      }
      if (!current || dirty[0] & /*onclick*/
      128 && article_tabindex_value !== (article_tabindex_value = /*onclick*/
      ctx[7] ? 0 : void 0)) {
        attr(article, "tabindex", article_tabindex_value);
      }
    },
    i(local) {
      if (current) return;
      if (switch_instance) transition_in(switch_instance.$$.fragment, local);
      current = true;
    },
    o(local) {
      if (switch_instance) transition_out(switch_instance.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(article);
      }
      if (switch_instance) destroy_component(switch_instance);
      ctx[53](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function instance$62($$self, $$props, $$invalidate) {
  let $eventClick;
  let $_view;
  let $eventAllUpdated;
  let $eventDidMount;
  let $_intlEventTime;
  let $theme;
  let $eventContent;
  let $displayEventEnd;
  let $eventClassNames;
  let $_iClasses;
  let $slotEventOverlap;
  let $eventTextColor;
  let $_resTxtColor;
  let $eventColor;
  let $eventBackgroundColor;
  let $_resBgColor;
  let $slotHeight;
  let $_slotTimeLimits;
  let $slotDuration;
  let $eventMouseEnter;
  let $eventMouseLeave;
  let $_interaction;
  let { date } = $$props;
  let { chunk } = $$props;
  let { displayEventEnd, eventAllUpdated, eventBackgroundColor, eventTextColor, eventColor, eventContent, eventClick, eventDidMount, eventClassNames, eventMouseEnter, eventMouseLeave, slotEventOverlap, slotDuration, slotHeight, theme, _view, _intlEventTime, _interaction, _iClasses, _resBgColor, _resTxtColor, _slotTimeLimits, _tasks } = getContext("state");
  component_subscribe($$self, displayEventEnd, (value) => $$invalidate(41, $displayEventEnd = value));
  component_subscribe($$self, eventAllUpdated, (value) => $$invalidate(55, $eventAllUpdated = value));
  component_subscribe($$self, eventBackgroundColor, (value) => $$invalidate(48, $eventBackgroundColor = value));
  component_subscribe($$self, eventTextColor, (value) => $$invalidate(45, $eventTextColor = value));
  component_subscribe($$self, eventColor, (value) => $$invalidate(47, $eventColor = value));
  component_subscribe($$self, eventContent, (value) => $$invalidate(40, $eventContent = value));
  component_subscribe($$self, eventClick, (value) => $$invalidate(37, $eventClick = value));
  component_subscribe($$self, eventDidMount, (value) => $$invalidate(56, $eventDidMount = value));
  component_subscribe($$self, eventClassNames, (value) => $$invalidate(42, $eventClassNames = value));
  component_subscribe($$self, eventMouseEnter, (value) => $$invalidate(8, $eventMouseEnter = value));
  component_subscribe($$self, eventMouseLeave, (value) => $$invalidate(9, $eventMouseLeave = value));
  component_subscribe($$self, slotEventOverlap, (value) => $$invalidate(44, $slotEventOverlap = value));
  component_subscribe($$self, slotDuration, (value) => $$invalidate(52, $slotDuration = value));
  component_subscribe($$self, slotHeight, (value) => $$invalidate(50, $slotHeight = value));
  component_subscribe($$self, theme, (value) => $$invalidate(2, $theme = value));
  component_subscribe($$self, _view, (value) => $$invalidate(38, $_view = value));
  component_subscribe($$self, _intlEventTime, (value) => $$invalidate(39, $_intlEventTime = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(10, $_interaction = value));
  component_subscribe($$self, _iClasses, (value) => $$invalidate(43, $_iClasses = value));
  component_subscribe($$self, _resBgColor, (value) => $$invalidate(49, $_resBgColor = value));
  component_subscribe($$self, _resTxtColor, (value) => $$invalidate(46, $_resTxtColor = value));
  component_subscribe($$self, _slotTimeLimits, (value) => $$invalidate(51, $_slotTimeLimits = value));
  let el;
  let event;
  let display;
  let classes;
  let style;
  let content;
  let timeText;
  let onclick;
  onMount(() => {
    if (is_function($eventDidMount)) {
      $eventDidMount({
        event: toEventWithLocalDates(event),
        timeText,
        el,
        view: toViewWithLocalDates($_view)
      });
    }
  });
  afterUpdate(() => {
    if (is_function($eventAllUpdated) && !helperEvent(display)) {
      task(() => $eventAllUpdated({ view: toViewWithLocalDates($_view) }), "eau", _tasks);
    }
  });
  function createHandler(fn, display2) {
    return !helperEvent(display2) && is_function(fn) ? (jsEvent) => fn({
      event: toEventWithLocalDates(event),
      el,
      jsEvent,
      view: toViewWithLocalDates($_view)
    }) : void 0;
  }
  function createDragHandler(interaction, resize) {
    return interaction.action ? (jsEvent) => interaction.action.drag(event, jsEvent, resize) : void 0;
  }
  function article_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(3, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("date" in $$props2) $$invalidate(35, date = $$props2.date);
    if ("chunk" in $$props2) $$invalidate(36, chunk = $$props2.chunk);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty[1] & /*chunk*/
    32) {
      $$invalidate(0, event = chunk.event);
    }
    if ($$self.$$.dirty[0] & /*event, style, display, $theme*/
    39 | $$self.$$.dirty[1] & /*$slotDuration, $_slotTimeLimits, chunk, date, $slotHeight, $_resBgColor, $eventBackgroundColor, $eventColor, $_resTxtColor, $eventTextColor, $slotEventOverlap, $_iClasses, $eventClassNames, $_view*/
    4192432) {
      {
        $$invalidate(1, display = event.display);
        let step = $slotDuration.seconds / 60;
        let offset = $_slotTimeLimits.min.seconds / 60;
        let start = (chunk.start - date) / 1e3 / 60;
        let end = (chunk.end - date) / 1e3 / 60;
        let top = (start - offset) / step * $slotHeight;
        let height2 = (end - start) / step * $slotHeight;
        let maxHeight = ($_slotTimeLimits.max.seconds / 60 - start) / step * $slotHeight;
        let bgColor = event.backgroundColor || $_resBgColor(event) || $eventBackgroundColor || $eventColor;
        let txtColor = event.textColor || $_resTxtColor(event) || $eventTextColor;
        $$invalidate(5, style = `top:${top}px;min-height:${height2}px;height:${height2}px;max-height:${maxHeight}px;`);
        if (bgColor) {
          $$invalidate(5, style += `background-color:${bgColor};`);
        }
        if (txtColor) {
          $$invalidate(5, style += `color:${txtColor};`);
        }
        if (!bgEvent(display) && !helperEvent(display) || ghostEvent(display)) {
          $$invalidate(5, style += `z-index:${chunk.column + 1};left:${100 / chunk.group.columns.length * chunk.column}%;width:${100 / chunk.group.columns.length * ($slotEventOverlap ? 0.5 * (1 + chunk.group.columns.length - chunk.column) : 1)}%;`);
        }
        $$invalidate(4, classes = [
          bgEvent(display) ? $theme.bgEvent : $theme.event,
          ...$_iClasses([], event),
          ...createEventClasses($eventClassNames, event, $_view)
        ].join(" "));
      }
    }
    if ($$self.$$.dirty[0] & /*$theme*/
    4 | $$self.$$.dirty[1] & /*chunk, $displayEventEnd, $eventContent, $_intlEventTime, $_view*/
    1952) {
      $$invalidate(6, [timeText, content] = createEventContent(chunk, $displayEventEnd, $eventContent, $theme, $_intlEventTime, $_view), content);
    }
    if ($$self.$$.dirty[0] & /*display*/
    2 | $$self.$$.dirty[1] & /*$eventClick*/
    64) {
      $$invalidate(7, onclick = !bgEvent(display) && createHandler($eventClick, display));
    }
  };
  return [
    event,
    display,
    $theme,
    el,
    classes,
    style,
    content,
    onclick,
    $eventMouseEnter,
    $eventMouseLeave,
    $_interaction,
    displayEventEnd,
    eventAllUpdated,
    eventBackgroundColor,
    eventTextColor,
    eventColor,
    eventContent,
    eventClick,
    eventDidMount,
    eventClassNames,
    eventMouseEnter,
    eventMouseLeave,
    slotEventOverlap,
    slotDuration,
    slotHeight,
    theme,
    _view,
    _intlEventTime,
    _interaction,
    _iClasses,
    _resBgColor,
    _resTxtColor,
    _slotTimeLimits,
    createHandler,
    createDragHandler,
    date,
    chunk,
    $eventClick,
    $_view,
    $_intlEventTime,
    $eventContent,
    $displayEventEnd,
    $eventClassNames,
    $_iClasses,
    $slotEventOverlap,
    $eventTextColor,
    $_resTxtColor,
    $eventColor,
    $eventBackgroundColor,
    $_resBgColor,
    $slotHeight,
    $_slotTimeLimits,
    $slotDuration,
    article_binding
  ];
}
var Event$1 = class Event2 extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$62, create_fragment$62, safe_not_equal, { date: 35, chunk: 36 }, null, [-1, -1]);
  }
};
function create_fragment$52(ctx) {
  let div;
  let div_class_value;
  return {
    c() {
      div = element("div");
      attr(div, "class", div_class_value = /*$theme*/
      ctx[1].nowIndicator);
      set_style(
        div,
        "top",
        /*top*/
        ctx[0] + "px"
      );
    },
    m(target, anchor) {
      insert(target, div, anchor);
    },
    p(ctx2, [dirty]) {
      if (dirty & /*$theme*/
      2 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[1].nowIndicator)) {
        attr(div, "class", div_class_value);
      }
      if (dirty & /*top*/
      1) {
        set_style(
          div,
          "top",
          /*top*/
          ctx2[0] + "px"
        );
      }
    },
    i: noop,
    o: noop,
    d(detaching) {
      if (detaching) {
        detach(div);
      }
    }
  };
}
function instance$52($$self, $$props, $$invalidate) {
  let $slotHeight;
  let $_slotTimeLimits;
  let $slotDuration;
  let $_today;
  let $_now;
  let $theme;
  let { slotDuration, slotHeight, theme, _now, _today, _slotTimeLimits } = getContext("state");
  component_subscribe($$self, slotDuration, (value) => $$invalidate(11, $slotDuration = value));
  component_subscribe($$self, slotHeight, (value) => $$invalidate(9, $slotHeight = value));
  component_subscribe($$self, theme, (value) => $$invalidate(1, $theme = value));
  component_subscribe($$self, _now, (value) => $$invalidate(13, $_now = value));
  component_subscribe($$self, _today, (value) => $$invalidate(12, $_today = value));
  component_subscribe($$self, _slotTimeLimits, (value) => $$invalidate(10, $_slotTimeLimits = value));
  let start;
  let top = 0;
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*$_now, $_today*/
    12288) {
      $$invalidate(8, start = ($_now - $_today) / 1e3 / 60);
    }
    if ($$self.$$.dirty & /*$slotDuration, $_slotTimeLimits, start, $slotHeight*/
    3840) {
      {
        let step = $slotDuration.seconds / 60;
        let offset = $_slotTimeLimits.min.seconds / 60;
        $$invalidate(0, top = (start - offset) / step * $slotHeight);
      }
    }
  };
  return [
    top,
    $theme,
    slotDuration,
    slotHeight,
    theme,
    _now,
    _today,
    _slotTimeLimits,
    start,
    $slotHeight,
    $_slotTimeLimits,
    $slotDuration,
    $_today,
    $_now
  ];
}
var NowIndicator = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$52, create_fragment$52, safe_not_equal, {});
  }
};
function get_each_context$32(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[33] = list[i];
  return child_ctx;
}
function get_each_context_1$1(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[33] = list[i];
  return child_ctx;
}
function create_each_block_1$1(key_1, ctx) {
  let first;
  let event;
  let current;
  event = new Event$1({
    props: {
      date: (
        /*date*/
        ctx[0]
      ),
      chunk: (
        /*chunk*/
        ctx[33]
      )
    }
  });
  return {
    key: key_1,
    first: null,
    c() {
      first = empty();
      create_component(event.$$.fragment);
      this.first = first;
    },
    m(target, anchor) {
      insert(target, first, anchor);
      mount_component(event, target, anchor);
      current = true;
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      const event_changes = {};
      if (dirty[0] & /*date*/
      1) event_changes.date = /*date*/
      ctx[0];
      if (dirty[0] & /*bgChunks*/
      8) event_changes.chunk = /*chunk*/
      ctx[33];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(first);
      }
      destroy_component(event, detaching);
    }
  };
}
function create_if_block_23(ctx) {
  let event;
  let current;
  event = new Event$1({
    props: {
      date: (
        /*date*/
        ctx[0]
      ),
      chunk: (
        /*iChunks*/
        ctx[4][1]
      )
    }
  });
  return {
    c() {
      create_component(event.$$.fragment);
    },
    m(target, anchor) {
      mount_component(event, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      const event_changes = {};
      if (dirty[0] & /*date*/
      1) event_changes.date = /*date*/
      ctx2[0];
      if (dirty[0] & /*iChunks*/
      16) event_changes.chunk = /*iChunks*/
      ctx2[4][1];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(event, detaching);
    }
  };
}
function create_each_block$32(key_1, ctx) {
  let first;
  let event;
  let current;
  event = new Event$1({
    props: {
      date: (
        /*date*/
        ctx[0]
      ),
      chunk: (
        /*chunk*/
        ctx[33]
      )
    }
  });
  return {
    key: key_1,
    first: null,
    c() {
      first = empty();
      create_component(event.$$.fragment);
      this.first = first;
    },
    m(target, anchor) {
      insert(target, first, anchor);
      mount_component(event, target, anchor);
      current = true;
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      const event_changes = {};
      if (dirty[0] & /*date*/
      1) event_changes.date = /*date*/
      ctx[0];
      if (dirty[0] & /*chunks*/
      4) event_changes.chunk = /*chunk*/
      ctx[33];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(first);
      }
      destroy_component(event, detaching);
    }
  };
}
function create_if_block_13(ctx) {
  let event;
  let current;
  event = new Event$1({
    props: {
      date: (
        /*date*/
        ctx[0]
      ),
      chunk: (
        /*iChunks*/
        ctx[4][0]
      )
    }
  });
  return {
    c() {
      create_component(event.$$.fragment);
    },
    m(target, anchor) {
      mount_component(event, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      const event_changes = {};
      if (dirty[0] & /*date*/
      1) event_changes.date = /*date*/
      ctx2[0];
      if (dirty[0] & /*iChunks*/
      16) event_changes.chunk = /*iChunks*/
      ctx2[4][0];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(event, detaching);
    }
  };
}
function create_if_block$2(ctx) {
  let nowindicator;
  let current;
  nowindicator = new NowIndicator({});
  return {
    c() {
      create_component(nowindicator.$$.fragment);
    },
    m(target, anchor) {
      mount_component(nowindicator, target, anchor);
      current = true;
    },
    i(local) {
      if (current) return;
      transition_in(nowindicator.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(nowindicator.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(nowindicator, detaching);
    }
  };
}
function create_fragment$42(ctx) {
  let div3;
  let div0;
  let each_blocks_1 = [];
  let each0_lookup = /* @__PURE__ */ new Map();
  let div0_class_value;
  let t0;
  let div1;
  let t1;
  let each_blocks = [];
  let each1_lookup = /* @__PURE__ */ new Map();
  let t2;
  let div1_class_value;
  let t3;
  let div2;
  let div2_class_value;
  let div3_class_value;
  let current;
  let mounted;
  let dispose;
  let each_value_1 = ensure_array_like(
    /*bgChunks*/
    ctx[3]
  );
  const get_key = (ctx2) => (
    /*chunk*/
    ctx2[33].event
  );
  for (let i = 0; i < each_value_1.length; i += 1) {
    let child_ctx = get_each_context_1$1(ctx, each_value_1, i);
    let key = get_key(child_ctx);
    each0_lookup.set(key, each_blocks_1[i] = create_each_block_1$1(key, child_ctx));
  }
  let if_block0 = (
    /*iChunks*/
    ctx[4][1] && create_if_block_23(ctx)
  );
  let each_value = ensure_array_like(
    /*chunks*/
    ctx[2]
  );
  const get_key_1 = (ctx2) => (
    /*chunk*/
    ctx2[33].event
  );
  for (let i = 0; i < each_value.length; i += 1) {
    let child_ctx = get_each_context$32(ctx, each_value, i);
    let key = get_key_1(child_ctx);
    each1_lookup.set(key, each_blocks[i] = create_each_block$32(key, child_ctx));
  }
  let if_block1 = (
    /*iChunks*/
    ctx[4][0] && !/*iChunks*/
    ctx[4][0].event.allDay && create_if_block_13(ctx)
  );
  let if_block2 = (
    /*$nowIndicator*/
    ctx[9] && /*isToday*/
    ctx[5] && create_if_block$2()
  );
  return {
    c() {
      div3 = element("div");
      div0 = element("div");
      for (let i = 0; i < each_blocks_1.length; i += 1) {
        each_blocks_1[i].c();
      }
      t0 = space();
      div1 = element("div");
      if (if_block0) if_block0.c();
      t1 = space();
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      t2 = space();
      if (if_block1) if_block1.c();
      t3 = space();
      div2 = element("div");
      if (if_block2) if_block2.c();
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[7].bgEvents);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[7].events);
      attr(div2, "class", div2_class_value = /*$theme*/
      ctx[7].extra);
      attr(div3, "class", div3_class_value = /*$theme*/
      ctx[7].day + " " + /*$theme*/
      ctx[7].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[5] ? " " + /*$theme*/
      ctx[7].today : "") + /*highlight*/
      (ctx[6] ? " " + /*$theme*/
      ctx[7].highlight : ""));
      attr(div3, "role", "cell");
    },
    m(target, anchor) {
      insert(target, div3, anchor);
      append(div3, div0);
      for (let i = 0; i < each_blocks_1.length; i += 1) {
        if (each_blocks_1[i]) {
          each_blocks_1[i].m(div0, null);
        }
      }
      append(div3, t0);
      append(div3, div1);
      if (if_block0) if_block0.m(div1, null);
      append(div1, t1);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div1, null);
        }
      }
      append(div1, t2);
      if (if_block1) if_block1.m(div1, null);
      append(div3, t3);
      append(div3, div2);
      if (if_block2) if_block2.m(div2, null);
      ctx[29](div3);
      current = true;
      if (!mounted) {
        dispose = [
          listen(div3, "pointerenter", function() {
            if (is_function(
              /*createPointerEnterHandler*/
              ctx[20](
                /*$_interaction*/
                ctx[8]
              )
            )) ctx[20](
              /*$_interaction*/
              ctx[8]
            ).apply(this, arguments);
          }),
          listen(div3, "pointerleave", function() {
            if (is_function(
              /*$_interaction*/
              ctx[8].pointer?.leave
            )) ctx[8].pointer?.leave.apply(this, arguments);
          }),
          listen(div3, "pointerdown", function() {
            if (is_function(
              /*$_interaction*/
              ctx[8].action?.select
            )) ctx[8].action?.select.apply(this, arguments);
          })
        ];
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (dirty[0] & /*date, bgChunks*/
      9) {
        each_value_1 = ensure_array_like(
          /*bgChunks*/
          ctx[3]
        );
        group_outros();
        each_blocks_1 = update_keyed_each(each_blocks_1, dirty, get_key, 1, ctx, each_value_1, each0_lookup, div0, outro_and_destroy_block, create_each_block_1$1, null, get_each_context_1$1);
        check_outros();
      }
      if (!current || dirty[0] & /*$theme*/
      128 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx[7].bgEvents)) {
        attr(div0, "class", div0_class_value);
      }
      if (
        /*iChunks*/
        ctx[4][1]
      ) {
        if (if_block0) {
          if_block0.p(ctx, dirty);
          if (dirty[0] & /*iChunks*/
          16) {
            transition_in(if_block0, 1);
          }
        } else {
          if_block0 = create_if_block_23(ctx);
          if_block0.c();
          transition_in(if_block0, 1);
          if_block0.m(div1, t1);
        }
      } else if (if_block0) {
        group_outros();
        transition_out(if_block0, 1, 1, () => {
          if_block0 = null;
        });
        check_outros();
      }
      if (dirty[0] & /*date, chunks*/
      5) {
        each_value = ensure_array_like(
          /*chunks*/
          ctx[2]
        );
        group_outros();
        each_blocks = update_keyed_each(each_blocks, dirty, get_key_1, 1, ctx, each_value, each1_lookup, div1, outro_and_destroy_block, create_each_block$32, t2, get_each_context$32);
        check_outros();
      }
      if (
        /*iChunks*/
        ctx[4][0] && !/*iChunks*/
        ctx[4][0].event.allDay
      ) {
        if (if_block1) {
          if_block1.p(ctx, dirty);
          if (dirty[0] & /*iChunks*/
          16) {
            transition_in(if_block1, 1);
          }
        } else {
          if_block1 = create_if_block_13(ctx);
          if_block1.c();
          transition_in(if_block1, 1);
          if_block1.m(div1, null);
        }
      } else if (if_block1) {
        group_outros();
        transition_out(if_block1, 1, 1, () => {
          if_block1 = null;
        });
        check_outros();
      }
      if (!current || dirty[0] & /*$theme*/
      128 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx[7].events)) {
        attr(div1, "class", div1_class_value);
      }
      if (
        /*$nowIndicator*/
        ctx[9] && /*isToday*/
        ctx[5]
      ) {
        if (if_block2) {
          if (dirty[0] & /*$nowIndicator, isToday*/
          544) {
            transition_in(if_block2, 1);
          }
        } else {
          if_block2 = create_if_block$2();
          if_block2.c();
          transition_in(if_block2, 1);
          if_block2.m(div2, null);
        }
      } else if (if_block2) {
        group_outros();
        transition_out(if_block2, 1, 1, () => {
          if_block2 = null;
        });
        check_outros();
      }
      if (!current || dirty[0] & /*$theme*/
      128 && div2_class_value !== (div2_class_value = /*$theme*/
      ctx[7].extra)) {
        attr(div2, "class", div2_class_value);
      }
      if (!current || dirty[0] & /*$theme, date, isToday, highlight*/
      225 && div3_class_value !== (div3_class_value = /*$theme*/
      ctx[7].day + " " + /*$theme*/
      ctx[7].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[5] ? " " + /*$theme*/
      ctx[7].today : "") + /*highlight*/
      (ctx[6] ? " " + /*$theme*/
      ctx[7].highlight : ""))) {
        attr(div3, "class", div3_class_value);
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value_1.length; i += 1) {
        transition_in(each_blocks_1[i]);
      }
      transition_in(if_block0);
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      transition_in(if_block1);
      transition_in(if_block2);
      current = true;
    },
    o(local) {
      for (let i = 0; i < each_blocks_1.length; i += 1) {
        transition_out(each_blocks_1[i]);
      }
      transition_out(if_block0);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      transition_out(if_block1);
      transition_out(if_block2);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div3);
      }
      for (let i = 0; i < each_blocks_1.length; i += 1) {
        each_blocks_1[i].d();
      }
      if (if_block0) if_block0.d();
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].d();
      }
      if (if_block1) if_block1.d();
      if (if_block2) if_block2.d();
      ctx[29](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function instance$42($$self, $$props, $$invalidate) {
  let $slotHeight;
  let $slotDuration;
  let $_slotTimeLimits;
  let $highlightedDates;
  let $_today;
  let $_iEvents;
  let $_events;
  let $theme;
  let $_interaction;
  let $nowIndicator;
  let { date } = $$props;
  let { resource = void 0 } = $$props;
  let { _events, _iEvents, highlightedDates, nowIndicator, slotDuration, slotHeight, theme, _interaction, _today, _slotTimeLimits } = getContext("state");
  component_subscribe($$self, _events, (value) => $$invalidate(28, $_events = value));
  component_subscribe($$self, _iEvents, (value) => $$invalidate(27, $_iEvents = value));
  component_subscribe($$self, highlightedDates, (value) => $$invalidate(25, $highlightedDates = value));
  component_subscribe($$self, nowIndicator, (value) => $$invalidate(9, $nowIndicator = value));
  component_subscribe($$self, slotDuration, (value) => $$invalidate(31, $slotDuration = value));
  component_subscribe($$self, slotHeight, (value) => $$invalidate(30, $slotHeight = value));
  component_subscribe($$self, theme, (value) => $$invalidate(7, $theme = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(8, $_interaction = value));
  component_subscribe($$self, _today, (value) => $$invalidate(26, $_today = value));
  component_subscribe($$self, _slotTimeLimits, (value) => $$invalidate(24, $_slotTimeLimits = value));
  let el;
  let chunks, bgChunks, iChunks = [];
  let isToday, highlight;
  let start, end;
  function dateFromPoint(y) {
    y -= rect(el).top;
    return {
      allDay: false,
      date: addDuration(addDuration(cloneDate(date), $_slotTimeLimits.min), $slotDuration, floor(y / $slotHeight)),
      resource,
      dayEl: el
    };
  }
  function createPointerEnterHandler(interaction) {
    return interaction.pointer ? (jsEvent) => interaction.pointer.enterTimeGrid(date, el, jsEvent, resource) : void 0;
  }
  function div3_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(1, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("date" in $$props2) $$invalidate(0, date = $$props2.date);
    if ("resource" in $$props2) $$invalidate(21, resource = $$props2.resource);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty[0] & /*date, $_slotTimeLimits*/
    16777217) {
      {
        $$invalidate(22, start = addDuration(cloneDate(date), $_slotTimeLimits.min));
        $$invalidate(23, end = addDuration(cloneDate(date), $_slotTimeLimits.max));
      }
    }
    if ($$self.$$.dirty[0] & /*$_events, start, end, resource, bgChunks, chunks*/
    283115532) {
      {
        $$invalidate(2, chunks = []);
        $$invalidate(3, bgChunks = []);
        for (let event of $_events) {
          if (!event.allDay && eventIntersects(event, start, end, resource, true)) {
            let chunk = createEventChunk(event, start, end);
            switch (event.display) {
              case "background":
                bgChunks.push(chunk);
                break;
              default:
                chunks.push(chunk);
            }
          }
        }
        groupEventChunks(chunks);
      }
    }
    if ($$self.$$.dirty[0] & /*$_iEvents, start, end, resource*/
    148897792) {
      $$invalidate(4, iChunks = $_iEvents.map((event) => event && eventIntersects(event, start, end, resource, true) ? createEventChunk(event, start, end) : null));
    }
    if ($$self.$$.dirty[0] & /*date, $_today*/
    67108865) {
      $$invalidate(5, isToday = datesEqual(date, $_today));
    }
    if ($$self.$$.dirty[0] & /*$highlightedDates, date*/
    33554433) {
      $$invalidate(6, highlight = $highlightedDates.some((d) => datesEqual(d, date)));
    }
    if ($$self.$$.dirty[0] & /*el*/
    2) {
      if (el) {
        setPayload(el, dateFromPoint);
      }
    }
  };
  return [
    date,
    el,
    chunks,
    bgChunks,
    iChunks,
    isToday,
    highlight,
    $theme,
    $_interaction,
    $nowIndicator,
    _events,
    _iEvents,
    highlightedDates,
    nowIndicator,
    slotDuration,
    slotHeight,
    theme,
    _interaction,
    _today,
    _slotTimeLimits,
    createPointerEnterHandler,
    resource,
    start,
    end,
    $_slotTimeLimits,
    $highlightedDates,
    $_today,
    $_iEvents,
    $_events,
    div3_binding
  ];
}
var Day$1 = class Day2 extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$42, create_fragment$42, safe_not_equal, { date: 0, resource: 21 }, null, [-1, -1]);
  }
};
function create_fragment$33(ctx) {
  let article;
  let div;
  let div_class_value;
  let setContent_action;
  let t;
  let switch_instance;
  let article_role_value;
  let article_tabindex_value;
  let current;
  let mounted;
  let dispose;
  var switch_value = (
    /*$_interaction*/
    ctx[10].resizer
  );
  function switch_props(ctx2, dirty) {
    return { props: { event: (
      /*event*/
      ctx2[0]
    ) } };
  }
  if (switch_value) {
    switch_instance = construct_svelte_component(switch_value, switch_props(ctx));
    switch_instance.$on("pointerdown", function() {
      if (is_function(
        /*createDragHandler*/
        ctx[30](
          /*$_interaction*/
          ctx[10],
          true
        )
      )) ctx[30](
        /*$_interaction*/
        ctx[10],
        true
      ).apply(this, arguments);
    });
  }
  return {
    c() {
      article = element("article");
      div = element("div");
      t = space();
      if (switch_instance) create_component(switch_instance.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[2].eventBody);
      attr(
        article,
        "class",
        /*classes*/
        ctx[4]
      );
      attr(
        article,
        "style",
        /*style*/
        ctx[5]
      );
      attr(article, "role", article_role_value = /*onclick*/
      ctx[7] ? "button" : void 0);
      attr(article, "tabindex", article_tabindex_value = /*onclick*/
      ctx[7] ? 0 : void 0);
    },
    m(target, anchor) {
      insert(target, article, anchor);
      append(article, div);
      append(article, t);
      if (switch_instance) mount_component(switch_instance, article, null);
      ctx[47](article);
      current = true;
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            div,
            /*content*/
            ctx[6]
          )),
          listen(article, "click", function() {
            if (is_function(
              /*onclick*/
              ctx[7]
            )) ctx[7].apply(this, arguments);
          }),
          listen(article, "keydown", function() {
            if (is_function(
              /*onclick*/
              ctx[7] && keyEnter(
                /*onclick*/
                ctx[7]
              )
            )) /*onclick*/
            (ctx[7] && keyEnter(
              /*onclick*/
              ctx[7]
            )).apply(this, arguments);
          }),
          listen(article, "mouseenter", function() {
            if (is_function(
              /*createHandler*/
              ctx[29](
                /*$eventMouseEnter*/
                ctx[8],
                /*display*/
                ctx[1]
              )
            )) ctx[29](
              /*$eventMouseEnter*/
              ctx[8],
              /*display*/
              ctx[1]
            ).apply(this, arguments);
          }),
          listen(article, "mouseleave", function() {
            if (is_function(
              /*createHandler*/
              ctx[29](
                /*$eventMouseLeave*/
                ctx[9],
                /*display*/
                ctx[1]
              )
            )) ctx[29](
              /*$eventMouseLeave*/
              ctx[9],
              /*display*/
              ctx[1]
            ).apply(this, arguments);
          }),
          listen(article, "pointerdown", function() {
            if (is_function(!helperEvent(
              /*display*/
              ctx[1]
            ) && /*createDragHandler*/
            ctx[30](
              /*$_interaction*/
              ctx[10]
            ))) (!helperEvent(
              /*display*/
              ctx[1]
            ) && /*createDragHandler*/
            ctx[30](
              /*$_interaction*/
              ctx[10]
            )).apply(this, arguments);
          })
        ];
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (!current || dirty[0] & /*$theme*/
      4 && div_class_value !== (div_class_value = /*$theme*/
      ctx[2].eventBody)) {
        attr(div, "class", div_class_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty[0] & /*content*/
      64) setContent_action.update.call(
        null,
        /*content*/
        ctx[6]
      );
      if (dirty[0] & /*$_interaction*/
      1024 && switch_value !== (switch_value = /*$_interaction*/
      ctx[10].resizer)) {
        if (switch_instance) {
          group_outros();
          const old_component = switch_instance;
          transition_out(old_component.$$.fragment, 1, 0, () => {
            destroy_component(old_component, 1);
          });
          check_outros();
        }
        if (switch_value) {
          switch_instance = construct_svelte_component(switch_value, switch_props(ctx));
          switch_instance.$on("pointerdown", function() {
            if (is_function(
              /*createDragHandler*/
              ctx[30](
                /*$_interaction*/
                ctx[10],
                true
              )
            )) ctx[30](
              /*$_interaction*/
              ctx[10],
              true
            ).apply(this, arguments);
          });
          create_component(switch_instance.$$.fragment);
          transition_in(switch_instance.$$.fragment, 1);
          mount_component(switch_instance, article, null);
        } else {
          switch_instance = null;
        }
      } else if (switch_value) {
        const switch_instance_changes = {};
        if (dirty[0] & /*event*/
        1) switch_instance_changes.event = /*event*/
        ctx[0];
        switch_instance.$set(switch_instance_changes);
      }
      if (!current || dirty[0] & /*classes*/
      16) {
        attr(
          article,
          "class",
          /*classes*/
          ctx[4]
        );
      }
      if (!current || dirty[0] & /*style*/
      32) {
        attr(
          article,
          "style",
          /*style*/
          ctx[5]
        );
      }
      if (!current || dirty[0] & /*onclick*/
      128 && article_role_value !== (article_role_value = /*onclick*/
      ctx[7] ? "button" : void 0)) {
        attr(article, "role", article_role_value);
      }
      if (!current || dirty[0] & /*onclick*/
      128 && article_tabindex_value !== (article_tabindex_value = /*onclick*/
      ctx[7] ? 0 : void 0)) {
        attr(article, "tabindex", article_tabindex_value);
      }
    },
    i(local) {
      if (current) return;
      if (switch_instance) transition_in(switch_instance.$$.fragment, local);
      current = true;
    },
    o(local) {
      if (switch_instance) transition_out(switch_instance.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(article);
      }
      if (switch_instance) destroy_component(switch_instance);
      ctx[47](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function instance$33($$self, $$props, $$invalidate) {
  let $eventClick;
  let $_view;
  let $eventAllUpdated;
  let $eventDidMount;
  let $_intlEventTime;
  let $theme;
  let $eventContent;
  let $displayEventEnd;
  let $eventClassNames;
  let $_iClasses;
  let $eventTextColor;
  let $_resTxtColor;
  let $eventColor;
  let $eventBackgroundColor;
  let $_resBgColor;
  let $eventMouseEnter;
  let $eventMouseLeave;
  let $_interaction;
  let { chunk } = $$props;
  let { longChunks = {} } = $$props;
  let { displayEventEnd, eventAllUpdated, eventBackgroundColor, eventTextColor, eventClick, eventColor, eventContent, eventClassNames, eventDidMount, eventMouseEnter, eventMouseLeave, theme, _view, _intlEventTime, _interaction, _iClasses, _resBgColor, _resTxtColor, _tasks } = getContext("state");
  component_subscribe($$self, displayEventEnd, (value) => $$invalidate(39, $displayEventEnd = value));
  component_subscribe($$self, eventAllUpdated, (value) => $$invalidate(49, $eventAllUpdated = value));
  component_subscribe($$self, eventBackgroundColor, (value) => $$invalidate(45, $eventBackgroundColor = value));
  component_subscribe($$self, eventTextColor, (value) => $$invalidate(42, $eventTextColor = value));
  component_subscribe($$self, eventClick, (value) => $$invalidate(35, $eventClick = value));
  component_subscribe($$self, eventColor, (value) => $$invalidate(44, $eventColor = value));
  component_subscribe($$self, eventContent, (value) => $$invalidate(38, $eventContent = value));
  component_subscribe($$self, eventClassNames, (value) => $$invalidate(40, $eventClassNames = value));
  component_subscribe($$self, eventDidMount, (value) => $$invalidate(50, $eventDidMount = value));
  component_subscribe($$self, eventMouseEnter, (value) => $$invalidate(8, $eventMouseEnter = value));
  component_subscribe($$self, eventMouseLeave, (value) => $$invalidate(9, $eventMouseLeave = value));
  component_subscribe($$self, theme, (value) => $$invalidate(2, $theme = value));
  component_subscribe($$self, _view, (value) => $$invalidate(36, $_view = value));
  component_subscribe($$self, _intlEventTime, (value) => $$invalidate(37, $_intlEventTime = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(10, $_interaction = value));
  component_subscribe($$self, _iClasses, (value) => $$invalidate(41, $_iClasses = value));
  component_subscribe($$self, _resBgColor, (value) => $$invalidate(46, $_resBgColor = value));
  component_subscribe($$self, _resTxtColor, (value) => $$invalidate(43, $_resTxtColor = value));
  let el;
  let event;
  let classes;
  let style;
  let content;
  let timeText;
  let margin = 1;
  let display;
  let onclick;
  onMount(() => {
    if (is_function($eventDidMount)) {
      $eventDidMount({
        event: toEventWithLocalDates(event),
        timeText,
        el,
        view: toViewWithLocalDates($_view)
      });
    }
  });
  afterUpdate(() => {
    if (is_function($eventAllUpdated) && !helperEvent(display)) {
      task(() => $eventAllUpdated({ view: toViewWithLocalDates($_view) }), "eau", _tasks);
    }
  });
  function createHandler(fn, display2) {
    return !helperEvent(display2) && is_function(fn) ? (jsEvent) => fn({
      event: toEventWithLocalDates(event),
      el,
      jsEvent,
      view: toViewWithLocalDates($_view)
    }) : void 0;
  }
  function createDragHandler(interaction, resize) {
    return interaction.action ? (jsEvent) => interaction.action.drag(event, jsEvent, resize) : void 0;
  }
  function reposition() {
    if (!el) {
      return;
    }
    $$invalidate(34, margin = repositionEvent(chunk, longChunks, height(el)));
  }
  function article_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(3, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("chunk" in $$props2) $$invalidate(31, chunk = $$props2.chunk);
    if ("longChunks" in $$props2) $$invalidate(32, longChunks = $$props2.longChunks);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty[1] & /*chunk*/
    1) {
      $$invalidate(0, event = chunk.event);
    }
    if ($$self.$$.dirty[0] & /*event, style, $theme*/
    37 | $$self.$$.dirty[1] & /*$_resBgColor, $eventBackgroundColor, $eventColor, $_resTxtColor, $eventTextColor, chunk, margin, $_iClasses, $eventClassNames, $_view*/
    65065) {
      {
        $$invalidate(1, display = event.display);
        let bgColor = event.backgroundColor || $_resBgColor(event) || $eventBackgroundColor || $eventColor;
        let txtColor = event.textColor || $_resTxtColor(event) || $eventTextColor;
        $$invalidate(5, style = `width:calc(${chunk.days * 100}% + ${(chunk.days - 1) * 7}px);margin-top:${margin}px;`);
        if (bgColor) {
          $$invalidate(5, style += `background-color:${bgColor};`);
        }
        if (txtColor) {
          $$invalidate(5, style += `color:${txtColor};`);
        }
        $$invalidate(4, classes = [
          $theme.event,
          ...$_iClasses([], event),
          ...createEventClasses($eventClassNames, event, $_view)
        ].join(" "));
      }
    }
    if ($$self.$$.dirty[0] & /*$theme*/
    4 | $$self.$$.dirty[1] & /*chunk, $displayEventEnd, $eventContent, $_intlEventTime, $_view*/
    481) {
      $$invalidate(6, [timeText, content] = createEventContent(chunk, $displayEventEnd, $eventContent, $theme, $_intlEventTime, $_view), content);
    }
    if ($$self.$$.dirty[0] & /*display*/
    2 | $$self.$$.dirty[1] & /*$eventClick*/
    16) {
      $$invalidate(7, onclick = createHandler($eventClick, display));
    }
  };
  return [
    event,
    display,
    $theme,
    el,
    classes,
    style,
    content,
    onclick,
    $eventMouseEnter,
    $eventMouseLeave,
    $_interaction,
    displayEventEnd,
    eventAllUpdated,
    eventBackgroundColor,
    eventTextColor,
    eventClick,
    eventColor,
    eventContent,
    eventClassNames,
    eventDidMount,
    eventMouseEnter,
    eventMouseLeave,
    theme,
    _view,
    _intlEventTime,
    _interaction,
    _iClasses,
    _resBgColor,
    _resTxtColor,
    createHandler,
    createDragHandler,
    chunk,
    longChunks,
    reposition,
    margin,
    $eventClick,
    $_view,
    $_intlEventTime,
    $eventContent,
    $displayEventEnd,
    $eventClassNames,
    $_iClasses,
    $eventTextColor,
    $_resTxtColor,
    $eventColor,
    $eventBackgroundColor,
    $_resBgColor,
    article_binding
  ];
}
var Event3 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(
      this,
      options,
      instance$33,
      create_fragment$33,
      safe_not_equal,
      {
        chunk: 31,
        longChunks: 32,
        reposition: 33
      },
      null,
      [-1, -1]
    );
  }
  get reposition() {
    return this.$$.ctx[33];
  }
};
function get_each_context$23(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[21] = list[i];
  child_ctx[22] = list;
  child_ctx[23] = i;
  return child_ctx;
}
function create_if_block$12(ctx) {
  let div;
  let event;
  let div_class_value;
  let current;
  event = new Event3({ props: { chunk: (
    /*iChunks*/
    ctx[2][0]
  ) } });
  return {
    c() {
      div = element("div");
      create_component(event.$$.fragment);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[8].events + " " + /*$theme*/
      ctx[8].preview);
    },
    m(target, anchor) {
      insert(target, div, anchor);
      mount_component(event, div, null);
      current = true;
    },
    p(ctx2, dirty) {
      const event_changes = {};
      if (dirty & /*iChunks*/
      4) event_changes.chunk = /*iChunks*/
      ctx2[2][0];
      event.$set(event_changes);
      if (!current || dirty & /*$theme*/
      256 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[8].events + " " + /*$theme*/
      ctx2[8].preview)) {
        attr(div, "class", div_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      destroy_component(event);
    }
  };
}
function create_each_block$23(key_1, ctx) {
  let first;
  let event;
  let i = (
    /*i*/
    ctx[23]
  );
  let current;
  const assign_event = () => (
    /*event_binding*/
    ctx[19](event, i)
  );
  const unassign_event = () => (
    /*event_binding*/
    ctx[19](null, i)
  );
  let event_props = {
    chunk: (
      /*chunk*/
      ctx[21]
    ),
    longChunks: (
      /*longChunks*/
      ctx[1]
    )
  };
  event = new Event3({ props: event_props });
  assign_event();
  return {
    key: key_1,
    first: null,
    c() {
      first = empty();
      create_component(event.$$.fragment);
      this.first = first;
    },
    m(target, anchor) {
      insert(target, first, anchor);
      mount_component(event, target, anchor);
      current = true;
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (i !== /*i*/
      ctx[23]) {
        unassign_event();
        i = /*i*/
        ctx[23];
        assign_event();
      }
      const event_changes = {};
      if (dirty & /*dayChunks*/
      16) event_changes.chunk = /*chunk*/
      ctx[21];
      if (dirty & /*longChunks*/
      2) event_changes.longChunks = /*longChunks*/
      ctx[1];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(first);
      }
      unassign_event();
      destroy_component(event, detaching);
    }
  };
}
function create_fragment$23(ctx) {
  let div1;
  let show_if = (
    /*iChunks*/
    ctx[2][0] && datesEqual(
      /*iChunks*/
      ctx[2][0].date,
      /*date*/
      ctx[0]
    )
  );
  let t;
  let div0;
  let each_blocks = [];
  let each_1_lookup = /* @__PURE__ */ new Map();
  let div0_class_value;
  let div1_class_value;
  let current;
  let mounted;
  let dispose;
  let if_block = show_if && create_if_block$12(ctx);
  let each_value = ensure_array_like(
    /*dayChunks*/
    ctx[4]
  );
  const get_key = (ctx2) => (
    /*chunk*/
    ctx2[21].event
  );
  for (let i = 0; i < each_value.length; i += 1) {
    let child_ctx = get_each_context$23(ctx, each_value, i);
    let key = get_key(child_ctx);
    each_1_lookup.set(key, each_blocks[i] = create_each_block$23(key, child_ctx));
  }
  return {
    c() {
      div1 = element("div");
      if (if_block) if_block.c();
      t = space();
      div0 = element("div");
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[8].events);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[8].day + " " + /*$theme*/
      ctx[8].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[5] ? " " + /*$theme*/
      ctx[8].today : "") + /*highlight*/
      (ctx[6] ? " " + /*$theme*/
      ctx[8].highlight : ""));
      attr(div1, "role", "cell");
    },
    m(target, anchor) {
      insert(target, div1, anchor);
      if (if_block) if_block.m(div1, null);
      append(div1, t);
      append(div1, div0);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div0, null);
        }
      }
      ctx[20](div1);
      current = true;
      if (!mounted) {
        dispose = listen(div1, "pointerdown", function() {
          if (is_function(
            /*$_interaction*/
            ctx[9].action?.select
          )) ctx[9].action?.select.apply(this, arguments);
        });
        mounted = true;
      }
    },
    p(new_ctx, [dirty]) {
      ctx = new_ctx;
      if (dirty & /*iChunks, date*/
      5) show_if = /*iChunks*/
      ctx[2][0] && datesEqual(
        /*iChunks*/
        ctx[2][0].date,
        /*date*/
        ctx[0]
      );
      if (show_if) {
        if (if_block) {
          if_block.p(ctx, dirty);
          if (dirty & /*iChunks, date*/
          5) {
            transition_in(if_block, 1);
          }
        } else {
          if_block = create_if_block$12(ctx);
          if_block.c();
          transition_in(if_block, 1);
          if_block.m(div1, t);
        }
      } else if (if_block) {
        group_outros();
        transition_out(if_block, 1, 1, () => {
          if_block = null;
        });
        check_outros();
      }
      if (dirty & /*dayChunks, longChunks, refs*/
      146) {
        each_value = ensure_array_like(
          /*dayChunks*/
          ctx[4]
        );
        group_outros();
        each_blocks = update_keyed_each(each_blocks, dirty, get_key, 1, ctx, each_value, each_1_lookup, div0, outro_and_destroy_block, create_each_block$23, null, get_each_context$23);
        check_outros();
      }
      if (!current || dirty & /*$theme*/
      256 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx[8].events)) {
        attr(div0, "class", div0_class_value);
      }
      if (!current || dirty & /*$theme, date, isToday, highlight*/
      353 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx[8].day + " " + /*$theme*/
      ctx[8].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[5] ? " " + /*$theme*/
      ctx[8].today : "") + /*highlight*/
      (ctx[6] ? " " + /*$theme*/
      ctx[8].highlight : ""))) {
        attr(div1, "class", div1_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(if_block);
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      transition_out(if_block);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div1);
      }
      if (if_block) if_block.d();
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].d();
      }
      ctx[20](null);
      mounted = false;
      dispose();
    }
  };
}
function instance$23($$self, $$props, $$invalidate) {
  let $highlightedDates;
  let $_today;
  let $theme;
  let $_interaction;
  let { date } = $$props;
  let { chunks } = $$props;
  let { longChunks } = $$props;
  let { iChunks = [] } = $$props;
  let { resource = void 0 } = $$props;
  let { highlightedDates, theme, _interaction, _today } = getContext("state");
  component_subscribe($$self, highlightedDates, (value) => $$invalidate(17, $highlightedDates = value));
  component_subscribe($$self, theme, (value) => $$invalidate(8, $theme = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(9, $_interaction = value));
  component_subscribe($$self, _today, (value) => $$invalidate(18, $_today = value));
  let el;
  let dayChunks;
  let isToday;
  let highlight;
  let refs = [];
  function reposition() {
    runReposition(refs, dayChunks);
  }
  function event_binding($$value, i) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      refs[i] = $$value;
      $$invalidate(7, refs);
    });
  }
  function div1_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(3, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("date" in $$props2) $$invalidate(0, date = $$props2.date);
    if ("chunks" in $$props2) $$invalidate(14, chunks = $$props2.chunks);
    if ("longChunks" in $$props2) $$invalidate(1, longChunks = $$props2.longChunks);
    if ("iChunks" in $$props2) $$invalidate(2, iChunks = $$props2.iChunks);
    if ("resource" in $$props2) $$invalidate(15, resource = $$props2.resource);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*chunks, date, dayChunks*/
    16401) {
      {
        $$invalidate(4, dayChunks = []);
        for (let chunk of chunks) {
          if (datesEqual(chunk.date, date)) {
            dayChunks.push(chunk);
          }
        }
      }
    }
    if ($$self.$$.dirty & /*date, $_today*/
    262145) {
      $$invalidate(5, isToday = datesEqual(date, $_today));
    }
    if ($$self.$$.dirty & /*$highlightedDates, date*/
    131073) {
      $$invalidate(6, highlight = $highlightedDates.some((d) => datesEqual(d, date)));
    }
    if ($$self.$$.dirty & /*el, date, resource*/
    32777) {
      if (el) {
        setPayload(el, () => ({ allDay: true, date, resource, dayEl: el }));
      }
    }
  };
  return [
    date,
    longChunks,
    iChunks,
    el,
    dayChunks,
    isToday,
    highlight,
    refs,
    $theme,
    $_interaction,
    highlightedDates,
    theme,
    _interaction,
    _today,
    chunks,
    resource,
    reposition,
    $highlightedDates,
    $_today,
    event_binding,
    div1_binding
  ];
}
var Day3 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$23, create_fragment$23, safe_not_equal, {
      date: 0,
      chunks: 14,
      longChunks: 1,
      iChunks: 2,
      resource: 15,
      reposition: 16
    });
  }
  get reposition() {
    return this.$$.ctx[16];
  }
};
function get_each_context$13(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[18] = list[i];
  child_ctx[19] = list;
  child_ctx[20] = i;
  return child_ctx;
}
function create_each_block$13(ctx) {
  let day;
  let i = (
    /*i*/
    ctx[20]
  );
  let current;
  const assign_day = () => (
    /*day_binding*/
    ctx[15](day, i)
  );
  const unassign_day = () => (
    /*day_binding*/
    ctx[15](null, i)
  );
  let day_props = {
    date: (
      /*date*/
      ctx[18]
    ),
    chunks: (
      /*chunks*/
      ctx[2]
    ),
    longChunks: (
      /*longChunks*/
      ctx[3]
    ),
    iChunks: (
      /*iChunks*/
      ctx[4]
    ),
    resource: (
      /*resource*/
      ctx[1]
    )
  };
  day = new Day3({ props: day_props });
  assign_day();
  return {
    c() {
      create_component(day.$$.fragment);
    },
    m(target, anchor) {
      mount_component(day, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      if (i !== /*i*/
      ctx2[20]) {
        unassign_day();
        i = /*i*/
        ctx2[20];
        assign_day();
      }
      const day_changes = {};
      if (dirty & /*dates*/
      1) day_changes.date = /*date*/
      ctx2[18];
      if (dirty & /*chunks*/
      4) day_changes.chunks = /*chunks*/
      ctx2[2];
      if (dirty & /*longChunks*/
      8) day_changes.longChunks = /*longChunks*/
      ctx2[3];
      if (dirty & /*iChunks*/
      16) day_changes.iChunks = /*iChunks*/
      ctx2[4];
      if (dirty & /*resource*/
      2) day_changes.resource = /*resource*/
      ctx2[1];
      day.$set(day_changes);
    },
    i(local) {
      if (current) return;
      transition_in(day.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(day.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      unassign_day();
      destroy_component(day, detaching);
    }
  };
}
function create_fragment$13(ctx) {
  let each_1_anchor;
  let current;
  let mounted;
  let dispose;
  let each_value = ensure_array_like(
    /*dates*/
    ctx[0]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block$13(get_each_context$13(ctx, each_value, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
      current = true;
      if (!mounted) {
        dispose = listen(
          window,
          "resize",
          /*reposition*/
          ctx[9]
        );
        mounted = true;
      }
    },
    p(ctx2, [dirty]) {
      if (dirty & /*dates, chunks, longChunks, iChunks, resource, refs*/
      63) {
        each_value = ensure_array_like(
          /*dates*/
          ctx2[0]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context$13(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block$13(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        group_outros();
        for (i = each_value.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
      mounted = false;
      dispose();
    }
  };
}
function instance$13($$self, $$props, $$invalidate) {
  let $hiddenDays;
  let $_iEvents;
  let $_events;
  let { dates } = $$props;
  let { resource = void 0 } = $$props;
  let { _events, _iEvents, _queue2, hiddenDays } = getContext("state");
  component_subscribe($$self, _events, (value) => $$invalidate(14, $_events = value));
  component_subscribe($$self, _iEvents, (value) => $$invalidate(13, $_iEvents = value));
  component_subscribe($$self, hiddenDays, (value) => $$invalidate(12, $hiddenDays = value));
  let chunks, longChunks, iChunks = [];
  let start;
  let end;
  let refs = [];
  let debounceHandle = {};
  function reposition() {
    debounce(() => runReposition(refs, dates), debounceHandle, _queue2);
  }
  function day_binding($$value, i) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      refs[i] = $$value;
      $$invalidate(5, refs);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("dates" in $$props2) $$invalidate(0, dates = $$props2.dates);
    if ("resource" in $$props2) $$invalidate(1, resource = $$props2.resource);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*dates*/
    1) {
      {
        $$invalidate(10, start = dates[0]);
        $$invalidate(11, end = addDay(cloneDate(dates[dates.length - 1])));
      }
    }
    if ($$self.$$.dirty & /*$_events, start, end, resource, chunks, $hiddenDays*/
    23558) {
      {
        $$invalidate(2, chunks = []);
        for (let event of $_events) {
          if (event.allDay && event.display !== "background" && eventIntersects(event, start, end, resource)) {
            let chunk = createEventChunk(event, start, end);
            chunks.push(chunk);
          }
        }
        $$invalidate(3, longChunks = prepareEventChunks(chunks, $hiddenDays));
        reposition();
      }
    }
    if ($$self.$$.dirty & /*$_iEvents, start, end, resource, $hiddenDays*/
    15362) {
      $$invalidate(4, iChunks = $_iEvents.map((event) => {
        let chunk;
        if (event && event.allDay && eventIntersects(event, start, end, resource)) {
          chunk = createEventChunk(event, start, end);
          prepareEventChunks([chunk], $hiddenDays);
        } else {
          chunk = null;
        }
        return chunk;
      }));
    }
  };
  return [
    dates,
    resource,
    chunks,
    longChunks,
    iChunks,
    refs,
    _events,
    _iEvents,
    hiddenDays,
    reposition,
    start,
    end,
    $hiddenDays,
    $_iEvents,
    $_events,
    day_binding
  ];
}
var Week2 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$13, create_fragment$13, safe_not_equal, { dates: 0, resource: 1 });
  }
};
function get_each_context3(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[10] = list[i];
  return child_ctx;
}
function get_each_context_12(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[10] = list[i];
  return child_ctx;
}
function create_each_block_12(ctx) {
  let div;
  let time;
  let time_datetime_value;
  let time_aria_label_value;
  let setContent_action;
  let t;
  let div_class_value;
  let mounted;
  let dispose;
  return {
    c() {
      div = element("div");
      time = element("time");
      t = space();
      attr(time, "datetime", time_datetime_value = toISOString(
        /*date*/
        ctx[10],
        10
      ));
      attr(time, "aria-label", time_aria_label_value = /*$_intlDayHeaderAL*/
      ctx[2].format(
        /*date*/
        ctx[10]
      ));
      attr(div, "class", div_class_value = /*$theme*/
      ctx[0].day + " " + /*$theme*/
      ctx[0].weekdays?.[
        /*date*/
        ctx[10].getUTCDay()
      ]);
      attr(div, "role", "columnheader");
    },
    m(target, anchor) {
      insert(target, div, anchor);
      append(div, time);
      append(div, t);
      if (!mounted) {
        dispose = action_destroyer(setContent_action = setContent.call(
          null,
          time,
          /*$_intlDayHeader*/
          ctx[3].format(
            /*date*/
            ctx[10]
          )
        ));
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (dirty & /*$_viewDates*/
      2 && time_datetime_value !== (time_datetime_value = toISOString(
        /*date*/
        ctx[10],
        10
      ))) {
        attr(time, "datetime", time_datetime_value);
      }
      if (dirty & /*$_intlDayHeaderAL, $_viewDates*/
      6 && time_aria_label_value !== (time_aria_label_value = /*$_intlDayHeaderAL*/
      ctx[2].format(
        /*date*/
        ctx[10]
      ))) {
        attr(time, "aria-label", time_aria_label_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*$_intlDayHeader, $_viewDates*/
      10) setContent_action.update.call(
        null,
        /*$_intlDayHeader*/
        ctx[3].format(
          /*date*/
          ctx[10]
        )
      );
      if (dirty & /*$theme, $_viewDates*/
      3 && div_class_value !== (div_class_value = /*$theme*/
      ctx[0].day + " " + /*$theme*/
      ctx[0].weekdays?.[
        /*date*/
        ctx[10].getUTCDay()
      ])) {
        attr(div, "class", div_class_value);
      }
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      mounted = false;
      dispose();
    }
  };
}
function create_default_slot_2(ctx) {
  let each_1_anchor;
  let each_value_1 = ensure_array_like(
    /*$_viewDates*/
    ctx[1]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value_1.length; i += 1) {
    each_blocks[i] = create_each_block_12(get_each_context_12(ctx, each_value_1, i));
  }
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme, $_viewDates, $_intlDayHeaderAL, $_intlDayHeader*/
      15) {
        each_value_1 = ensure_array_like(
          /*$_viewDates*/
          ctx2[1]
        );
        let i;
        for (i = 0; i < each_value_1.length; i += 1) {
          const child_ctx = get_each_context_12(ctx2, each_value_1, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
          } else {
            each_blocks[i] = create_each_block_12(child_ctx);
            each_blocks[i].c();
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        for (; i < each_blocks.length; i += 1) {
          each_blocks[i].d(1);
        }
        each_blocks.length = each_value_1.length;
      }
    },
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function create_if_block3(ctx) {
  let div2;
  let div1;
  let section;
  let t;
  let div0;
  let div0_class_value;
  let div1_class_value;
  let div2_class_value;
  let current;
  section = new Section({
    props: {
      $$slots: { default: [create_default_slot_1] },
      $$scope: { ctx }
    }
  });
  return {
    c() {
      div2 = element("div");
      div1 = element("div");
      create_component(section.$$.fragment);
      t = space();
      div0 = element("div");
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[0].hiddenScroll);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[0].content);
      attr(div2, "class", div2_class_value = /*$theme*/
      ctx[0].allDay);
    },
    m(target, anchor) {
      insert(target, div2, anchor);
      append(div2, div1);
      mount_component(section, div1, null);
      append(div1, t);
      append(div1, div0);
      current = true;
    },
    p(ctx2, dirty) {
      const section_changes = {};
      if (dirty & /*$$scope, $_viewDates*/
      32770) {
        section_changes.$$scope = { dirty, ctx: ctx2 };
      }
      section.$set(section_changes);
      if (!current || dirty & /*$theme*/
      1 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[0].hiddenScroll)) {
        attr(div0, "class", div0_class_value);
      }
      if (!current || dirty & /*$theme*/
      1 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[0].content)) {
        attr(div1, "class", div1_class_value);
      }
      if (!current || dirty & /*$theme*/
      1 && div2_class_value !== (div2_class_value = /*$theme*/
      ctx2[0].allDay)) {
        attr(div2, "class", div2_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(section.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(section.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div2);
      }
      destroy_component(section);
    }
  };
}
function create_default_slot_1(ctx) {
  let week;
  let current;
  week = new Week2({ props: { dates: (
    /*$_viewDates*/
    ctx[1]
  ) } });
  return {
    c() {
      create_component(week.$$.fragment);
    },
    m(target, anchor) {
      mount_component(week, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      const week_changes = {};
      if (dirty & /*$_viewDates*/
      2) week_changes.dates = /*$_viewDates*/
      ctx2[1];
      week.$set(week_changes);
    },
    i(local) {
      if (current) return;
      transition_in(week.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(week.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(week, detaching);
    }
  };
}
function create_each_block3(ctx) {
  let day;
  let current;
  day = new Day$1({ props: { date: (
    /*date*/
    ctx[10]
  ) } });
  return {
    c() {
      create_component(day.$$.fragment);
    },
    m(target, anchor) {
      mount_component(day, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      const day_changes = {};
      if (dirty & /*$_viewDates*/
      2) day_changes.date = /*date*/
      ctx2[10];
      day.$set(day_changes);
    },
    i(local) {
      if (current) return;
      transition_in(day.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(day.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(day, detaching);
    }
  };
}
function create_default_slot2(ctx) {
  let each_1_anchor;
  let current;
  let each_value = ensure_array_like(
    /*$_viewDates*/
    ctx[1]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block3(get_each_context3(ctx, each_value, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      if (dirty & /*$_viewDates*/
      2) {
        each_value = ensure_array_like(
          /*$_viewDates*/
          ctx2[1]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context3(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block3(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        group_outros();
        for (i = each_value.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function create_fragment3(ctx) {
  let div1;
  let section;
  let t0;
  let div0;
  let div0_class_value;
  let div1_class_value;
  let t1;
  let t2;
  let body;
  let current;
  section = new Section({
    props: {
      $$slots: { default: [create_default_slot_2] },
      $$scope: { ctx }
    }
  });
  let if_block = (
    /*$allDaySlot*/
    ctx[4] && create_if_block3(ctx)
  );
  body = new Body2({
    props: {
      $$slots: { default: [create_default_slot2] },
      $$scope: { ctx }
    }
  });
  return {
    c() {
      div1 = element("div");
      create_component(section.$$.fragment);
      t0 = space();
      div0 = element("div");
      t1 = space();
      if (if_block) if_block.c();
      t2 = space();
      create_component(body.$$.fragment);
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[0].hiddenScroll);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[0].header);
    },
    m(target, anchor) {
      insert(target, div1, anchor);
      mount_component(section, div1, null);
      append(div1, t0);
      append(div1, div0);
      insert(target, t1, anchor);
      if (if_block) if_block.m(target, anchor);
      insert(target, t2, anchor);
      mount_component(body, target, anchor);
      current = true;
    },
    p(ctx2, [dirty]) {
      const section_changes = {};
      if (dirty & /*$$scope, $_viewDates, $theme, $_intlDayHeaderAL, $_intlDayHeader*/
      32783) {
        section_changes.$$scope = { dirty, ctx: ctx2 };
      }
      section.$set(section_changes);
      if (!current || dirty & /*$theme*/
      1 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[0].hiddenScroll)) {
        attr(div0, "class", div0_class_value);
      }
      if (!current || dirty & /*$theme*/
      1 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[0].header)) {
        attr(div1, "class", div1_class_value);
      }
      if (
        /*$allDaySlot*/
        ctx2[4]
      ) {
        if (if_block) {
          if_block.p(ctx2, dirty);
          if (dirty & /*$allDaySlot*/
          16) {
            transition_in(if_block, 1);
          }
        } else {
          if_block = create_if_block3(ctx2);
          if_block.c();
          transition_in(if_block, 1);
          if_block.m(t2.parentNode, t2);
        }
      } else if (if_block) {
        group_outros();
        transition_out(if_block, 1, 1, () => {
          if_block = null;
        });
        check_outros();
      }
      const body_changes = {};
      if (dirty & /*$$scope, $_viewDates*/
      32770) {
        body_changes.$$scope = { dirty, ctx: ctx2 };
      }
      body.$set(body_changes);
    },
    i(local) {
      if (current) return;
      transition_in(section.$$.fragment, local);
      transition_in(if_block);
      transition_in(body.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(section.$$.fragment, local);
      transition_out(if_block);
      transition_out(body.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div1);
        detach(t1);
        detach(t2);
      }
      destroy_component(section);
      if (if_block) if_block.d(detaching);
      destroy_component(body, detaching);
    }
  };
}
function instance3($$self, $$props, $$invalidate) {
  let $theme;
  let $_viewDates;
  let $_intlDayHeaderAL;
  let $_intlDayHeader;
  let $allDaySlot;
  let { _viewDates, _intlDayHeader, _intlDayHeaderAL, allDaySlot, theme } = getContext("state");
  component_subscribe($$self, _viewDates, (value) => $$invalidate(1, $_viewDates = value));
  component_subscribe($$self, _intlDayHeader, (value) => $$invalidate(3, $_intlDayHeader = value));
  component_subscribe($$self, _intlDayHeaderAL, (value) => $$invalidate(2, $_intlDayHeaderAL = value));
  component_subscribe($$self, allDaySlot, (value) => $$invalidate(4, $allDaySlot = value));
  component_subscribe($$self, theme, (value) => $$invalidate(0, $theme = value));
  return [
    $theme,
    $_viewDates,
    $_intlDayHeaderAL,
    $_intlDayHeader,
    $allDaySlot,
    _viewDates,
    _intlDayHeader,
    _intlDayHeaderAL,
    allDaySlot,
    theme
  ];
}
var View2 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance3, create_fragment3, safe_not_equal, {});
  }
};
var index2 = {
  createOptions(options) {
    options.buttonText.timeGridDay = "day";
    options.buttonText.timeGridWeek = "week";
    options.view = "timeGridWeek";
    options.views.timeGridDay = {
      buttonText: btnTextDay,
      component: View2,
      dayHeaderFormat: { weekday: "long" },
      duration: { days: 1 },
      theme: themeView("ec-time-grid ec-day-view"),
      titleFormat: { year: "numeric", month: "long", day: "numeric" }
    };
    options.views.timeGridWeek = {
      buttonText: btnTextWeek,
      component: View2,
      duration: { weeks: 1 },
      theme: themeView("ec-time-grid ec-week-view")
    };
  },
  createStores(state) {
    state._slotTimeLimits = slotTimeLimits(state);
    state._times = times(state);
  }
};

// node_modules/@event-calendar/list/index.js
function create_fragment$34(ctx) {
  let div1;
  let div0;
  let div0_class_value;
  let div1_class_value;
  let current;
  const default_slot_template = (
    /*#slots*/
    ctx[5].default
  );
  const default_slot = create_slot(
    default_slot_template,
    ctx,
    /*$$scope*/
    ctx[4],
    null
  );
  return {
    c() {
      div1 = element("div");
      div0 = element("div");
      if (default_slot) default_slot.c();
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[0].content);
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[0].body);
    },
    m(target, anchor) {
      insert(target, div1, anchor);
      append(div1, div0);
      if (default_slot) {
        default_slot.m(div0, null);
      }
      ctx[6](div1);
      current = true;
    },
    p(ctx2, [dirty]) {
      if (default_slot) {
        if (default_slot.p && (!current || dirty & /*$$scope*/
        16)) {
          update_slot_base(
            default_slot,
            default_slot_template,
            ctx2,
            /*$$scope*/
            ctx2[4],
            !current ? get_all_dirty_from_scope(
              /*$$scope*/
              ctx2[4]
            ) : get_slot_changes(
              default_slot_template,
              /*$$scope*/
              ctx2[4],
              dirty,
              null
            ),
            null
          );
        }
      }
      if (!current || dirty & /*$theme*/
      1 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx2[0].content)) {
        attr(div0, "class", div0_class_value);
      }
      if (!current || dirty & /*$theme*/
      1 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx2[0].body)) {
        attr(div1, "class", div1_class_value);
      }
    },
    i(local) {
      if (current) return;
      transition_in(default_slot, local);
      current = true;
    },
    o(local) {
      transition_out(default_slot, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div1);
      }
      if (default_slot) default_slot.d(detaching);
      ctx[6](null);
    }
  };
}
function instance$34($$self, $$props, $$invalidate) {
  let $theme;
  let $_bodyEl;
  let { $$slots: slots = {}, $$scope } = $$props;
  let { _bodyEl, theme } = getContext("state");
  component_subscribe($$self, _bodyEl, (value) => $$invalidate(1, $_bodyEl = value));
  component_subscribe($$self, theme, (value) => $$invalidate(0, $theme = value));
  function div1_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      $_bodyEl = $$value;
      _bodyEl.set($_bodyEl);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("$$scope" in $$props2) $$invalidate(4, $$scope = $$props2.$$scope);
  };
  return [$theme, $_bodyEl, _bodyEl, theme, $$scope, slots, div1_binding];
}
var Body3 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$34, create_fragment$34, safe_not_equal, {});
  }
};
function create_fragment$24(ctx) {
  let article;
  let div0;
  let div0_class_value;
  let t;
  let div1;
  let div1_class_value;
  let setContent_action;
  let article_role_value;
  let article_tabindex_value;
  let mounted;
  let dispose;
  return {
    c() {
      article = element("article");
      div0 = element("div");
      t = space();
      div1 = element("div");
      attr(div0, "class", div0_class_value = /*$theme*/
      ctx[0].eventTag);
      attr(
        div0,
        "style",
        /*style*/
        ctx[3]
      );
      attr(div1, "class", div1_class_value = /*$theme*/
      ctx[0].eventBody);
      attr(
        article,
        "class",
        /*classes*/
        ctx[2]
      );
      attr(article, "role", article_role_value = /*onclick*/
      ctx[5] ? "button" : void 0);
      attr(article, "tabindex", article_tabindex_value = /*onclick*/
      ctx[5] ? 0 : void 0);
    },
    m(target, anchor) {
      insert(target, article, anchor);
      append(article, div0);
      append(article, t);
      append(article, div1);
      ctx[40](article);
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            div1,
            /*content*/
            ctx[4]
          )),
          listen(article, "click", function() {
            if (is_function(
              /*onclick*/
              ctx[5]
            )) ctx[5].apply(this, arguments);
          }),
          listen(article, "keydown", function() {
            if (is_function(
              /*onclick*/
              ctx[5] && keyEnter(
                /*onclick*/
                ctx[5]
              )
            )) /*onclick*/
            (ctx[5] && keyEnter(
              /*onclick*/
              ctx[5]
            )).apply(this, arguments);
          }),
          listen(article, "mouseenter", function() {
            if (is_function(
              /*createHandler*/
              ctx[26](
                /*$eventMouseEnter*/
                ctx[6]
              )
            )) ctx[26](
              /*$eventMouseEnter*/
              ctx[6]
            ).apply(this, arguments);
          }),
          listen(article, "mouseleave", function() {
            if (is_function(
              /*createHandler*/
              ctx[26](
                /*$eventMouseLeave*/
                ctx[7]
              )
            )) ctx[26](
              /*$eventMouseLeave*/
              ctx[7]
            ).apply(this, arguments);
          }),
          listen(article, "pointerdown", function() {
            if (is_function(
              /*$_interaction*/
              ctx[8].action?.noAction
            )) ctx[8].action?.noAction.apply(this, arguments);
          })
        ];
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (dirty[0] & /*$theme*/
      1 && div0_class_value !== (div0_class_value = /*$theme*/
      ctx[0].eventTag)) {
        attr(div0, "class", div0_class_value);
      }
      if (dirty[0] & /*style*/
      8) {
        attr(
          div0,
          "style",
          /*style*/
          ctx[3]
        );
      }
      if (dirty[0] & /*$theme*/
      1 && div1_class_value !== (div1_class_value = /*$theme*/
      ctx[0].eventBody)) {
        attr(div1, "class", div1_class_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty[0] & /*content*/
      16) setContent_action.update.call(
        null,
        /*content*/
        ctx[4]
      );
      if (dirty[0] & /*classes*/
      4) {
        attr(
          article,
          "class",
          /*classes*/
          ctx[2]
        );
      }
      if (dirty[0] & /*onclick*/
      32 && article_role_value !== (article_role_value = /*onclick*/
      ctx[5] ? "button" : void 0)) {
        attr(article, "role", article_role_value);
      }
      if (dirty[0] & /*onclick*/
      32 && article_tabindex_value !== (article_tabindex_value = /*onclick*/
      ctx[5] ? 0 : void 0)) {
        attr(article, "tabindex", article_tabindex_value);
      }
    },
    i: noop,
    o: noop,
    d(detaching) {
      if (detaching) {
        detach(article);
      }
      ctx[40](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function instance$24($$self, $$props, $$invalidate) {
  let $eventClick;
  let $_view;
  let $eventAllUpdated;
  let $eventDidMount;
  let $_intlEventTime;
  let $theme;
  let $eventContent;
  let $displayEventEnd;
  let $eventClassNames;
  let $eventTextColor;
  let $_resTxtColor;
  let $eventColor;
  let $eventBackgroundColor;
  let $_resBgColor;
  let $eventMouseEnter;
  let $eventMouseLeave;
  let $_interaction;
  let { chunk } = $$props;
  let { displayEventEnd, eventAllUpdated, eventBackgroundColor, eventTextColor, eventColor, eventContent, eventClassNames, eventClick, eventDidMount, eventMouseEnter, eventMouseLeave, theme, _view, _intlEventTime, _resBgColor, _resTxtColor, _interaction, _tasks } = getContext("state");
  component_subscribe($$self, displayEventEnd, (value) => $$invalidate(33, $displayEventEnd = value));
  component_subscribe($$self, eventAllUpdated, (value) => $$invalidate(42, $eventAllUpdated = value));
  component_subscribe($$self, eventBackgroundColor, (value) => $$invalidate(38, $eventBackgroundColor = value));
  component_subscribe($$self, eventTextColor, (value) => $$invalidate(35, $eventTextColor = value));
  component_subscribe($$self, eventColor, (value) => $$invalidate(37, $eventColor = value));
  component_subscribe($$self, eventContent, (value) => $$invalidate(32, $eventContent = value));
  component_subscribe($$self, eventClassNames, (value) => $$invalidate(34, $eventClassNames = value));
  component_subscribe($$self, eventClick, (value) => $$invalidate(29, $eventClick = value));
  component_subscribe($$self, eventDidMount, (value) => $$invalidate(43, $eventDidMount = value));
  component_subscribe($$self, eventMouseEnter, (value) => $$invalidate(6, $eventMouseEnter = value));
  component_subscribe($$self, eventMouseLeave, (value) => $$invalidate(7, $eventMouseLeave = value));
  component_subscribe($$self, theme, (value) => $$invalidate(0, $theme = value));
  component_subscribe($$self, _view, (value) => $$invalidate(30, $_view = value));
  component_subscribe($$self, _intlEventTime, (value) => $$invalidate(31, $_intlEventTime = value));
  component_subscribe($$self, _resBgColor, (value) => $$invalidate(39, $_resBgColor = value));
  component_subscribe($$self, _resTxtColor, (value) => $$invalidate(36, $_resTxtColor = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(8, $_interaction = value));
  let el;
  let event;
  let classes;
  let style;
  let content;
  let timeText;
  let onclick;
  onMount(() => {
    if (is_function($eventDidMount)) {
      $eventDidMount({
        event: toEventWithLocalDates(event),
        timeText,
        el,
        view: toViewWithLocalDates($_view)
      });
    }
  });
  afterUpdate(() => {
    if (is_function($eventAllUpdated)) {
      task(() => $eventAllUpdated({ view: toViewWithLocalDates($_view) }), "eau", _tasks);
    }
  });
  function createHandler(fn) {
    return is_function(fn) ? (jsEvent) => fn({
      event: toEventWithLocalDates(event),
      el,
      jsEvent,
      view: toViewWithLocalDates($_view)
    }) : void 0;
  }
  function article_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(1, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("chunk" in $$props2) $$invalidate(27, chunk = $$props2.chunk);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty[0] & /*chunk*/
    134217728) {
      $$invalidate(28, event = chunk.event);
    }
    if ($$self.$$.dirty[0] & /*event, style, $theme, $_view*/
    1342177289 | $$self.$$.dirty[1] & /*$_resBgColor, $eventBackgroundColor, $eventColor, $_resTxtColor, $eventTextColor, $eventClassNames*/
    504) {
      {
        $$invalidate(3, style = "");
        let bgColor = event.backgroundColor || $_resBgColor(event) || $eventBackgroundColor || $eventColor;
        if (bgColor) {
          $$invalidate(3, style = `background-color:${bgColor};`);
        }
        let txtColor = event.textColor || $_resTxtColor(event) || $eventTextColor;
        if (txtColor) {
          $$invalidate(3, style += `color:${txtColor};`);
        }
        $$invalidate(2, classes = [$theme.event, ...createEventClasses($eventClassNames, event, $_view)].join(" "));
      }
    }
    if ($$self.$$.dirty[0] & /*chunk, $theme, $_view*/
    1207959553 | $$self.$$.dirty[1] & /*$displayEventEnd, $eventContent, $_intlEventTime*/
    7) {
      {
        $$invalidate(4, [timeText, content] = createEventContent(chunk, $displayEventEnd, $eventContent, $theme, $_intlEventTime, $_view), content);
      }
    }
    if ($$self.$$.dirty[0] & /*$eventClick*/
    536870912) {
      $$invalidate(5, onclick = createHandler($eventClick));
    }
  };
  return [
    $theme,
    el,
    classes,
    style,
    content,
    onclick,
    $eventMouseEnter,
    $eventMouseLeave,
    $_interaction,
    displayEventEnd,
    eventAllUpdated,
    eventBackgroundColor,
    eventTextColor,
    eventColor,
    eventContent,
    eventClassNames,
    eventClick,
    eventDidMount,
    eventMouseEnter,
    eventMouseLeave,
    theme,
    _view,
    _intlEventTime,
    _resBgColor,
    _resTxtColor,
    _interaction,
    createHandler,
    chunk,
    event,
    $eventClick,
    $_view,
    $_intlEventTime,
    $eventContent,
    $displayEventEnd,
    $eventClassNames,
    $eventTextColor,
    $_resTxtColor,
    $eventColor,
    $eventBackgroundColor,
    $_resBgColor,
    article_binding
  ];
}
var Event4 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$24, create_fragment$24, safe_not_equal, { chunk: 27 }, null, [-1, -1]);
  }
};
function get_each_context$14(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[21] = list[i];
  return child_ctx;
}
function create_if_block$13(ctx) {
  let div;
  let h4;
  let time0;
  let setContent_action;
  let t0;
  let time1;
  let time1_class_value;
  let setContent_action_1;
  let h4_class_value;
  let t1;
  let each_blocks = [];
  let each_1_lookup = /* @__PURE__ */ new Map();
  let div_class_value;
  let current;
  let mounted;
  let dispose;
  let each_value = ensure_array_like(
    /*chunks*/
    ctx[2]
  );
  const get_key = (ctx2) => (
    /*chunk*/
    ctx2[21].event
  );
  for (let i = 0; i < each_value.length; i += 1) {
    let child_ctx = get_each_context$14(ctx, each_value, i);
    let key = get_key(child_ctx);
    each_1_lookup.set(key, each_blocks[i] = create_each_block$14(key, child_ctx));
  }
  return {
    c() {
      div = element("div");
      h4 = element("h4");
      time0 = element("time");
      t0 = space();
      time1 = element("time");
      t1 = space();
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      attr(
        time0,
        "datetime",
        /*datetime*/
        ctx[5]
      );
      attr(time1, "class", time1_class_value = /*$theme*/
      ctx[6].daySide);
      attr(
        time1,
        "datetime",
        /*datetime*/
        ctx[5]
      );
      attr(h4, "class", h4_class_value = /*$theme*/
      ctx[6].dayHead);
      attr(div, "class", div_class_value = /*$theme*/
      ctx[6].day + " " + /*$theme*/
      ctx[6].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[3] ? " " + /*$theme*/
      ctx[6].today : "") + /*highlight*/
      (ctx[4] ? " " + /*$theme*/
      ctx[6].highlight : ""));
      attr(div, "role", "listitem");
    },
    m(target, anchor) {
      insert(target, div, anchor);
      append(div, h4);
      append(h4, time0);
      append(h4, t0);
      append(h4, time1);
      append(div, t1);
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(div, null);
        }
      }
      ctx[20](div);
      current = true;
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            time0,
            /*$_intlListDay*/
            ctx[8].format(
              /*date*/
              ctx[0]
            )
          )),
          action_destroyer(setContent_action_1 = setContent.call(
            null,
            time1,
            /*$_intlListDaySide*/
            ctx[9].format(
              /*date*/
              ctx[0]
            )
          )),
          listen(div, "pointerdown", function() {
            if (is_function(
              /*$_interaction*/
              ctx[7].action?.select
            )) ctx[7].action?.select.apply(this, arguments);
          })
        ];
        mounted = true;
      }
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      if (!current || dirty & /*datetime*/
      32) {
        attr(
          time0,
          "datetime",
          /*datetime*/
          ctx[5]
        );
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*$_intlListDay, date*/
      257) setContent_action.update.call(
        null,
        /*$_intlListDay*/
        ctx[8].format(
          /*date*/
          ctx[0]
        )
      );
      if (!current || dirty & /*$theme*/
      64 && time1_class_value !== (time1_class_value = /*$theme*/
      ctx[6].daySide)) {
        attr(time1, "class", time1_class_value);
      }
      if (!current || dirty & /*datetime*/
      32) {
        attr(
          time1,
          "datetime",
          /*datetime*/
          ctx[5]
        );
      }
      if (setContent_action_1 && is_function(setContent_action_1.update) && dirty & /*$_intlListDaySide, date*/
      513) setContent_action_1.update.call(
        null,
        /*$_intlListDaySide*/
        ctx[9].format(
          /*date*/
          ctx[0]
        )
      );
      if (!current || dirty & /*$theme*/
      64 && h4_class_value !== (h4_class_value = /*$theme*/
      ctx[6].dayHead)) {
        attr(h4, "class", h4_class_value);
      }
      if (dirty & /*chunks*/
      4) {
        each_value = ensure_array_like(
          /*chunks*/
          ctx[2]
        );
        group_outros();
        each_blocks = update_keyed_each(each_blocks, dirty, get_key, 1, ctx, each_value, each_1_lookup, div, outro_and_destroy_block, create_each_block$14, null, get_each_context$14);
        check_outros();
      }
      if (!current || dirty & /*$theme, date, isToday, highlight*/
      89 && div_class_value !== (div_class_value = /*$theme*/
      ctx[6].day + " " + /*$theme*/
      ctx[6].weekdays?.[
        /*date*/
        ctx[0].getUTCDay()
      ] + /*isToday*/
      (ctx[3] ? " " + /*$theme*/
      ctx[6].today : "") + /*highlight*/
      (ctx[4] ? " " + /*$theme*/
      ctx[6].highlight : ""))) {
        attr(div, "class", div_class_value);
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].d();
      }
      ctx[20](null);
      mounted = false;
      run_all(dispose);
    }
  };
}
function create_each_block$14(key_1, ctx) {
  let first;
  let event;
  let current;
  event = new Event4({ props: { chunk: (
    /*chunk*/
    ctx[21]
  ) } });
  return {
    key: key_1,
    first: null,
    c() {
      first = empty();
      create_component(event.$$.fragment);
      this.first = first;
    },
    m(target, anchor) {
      insert(target, first, anchor);
      mount_component(event, target, anchor);
      current = true;
    },
    p(new_ctx, dirty) {
      ctx = new_ctx;
      const event_changes = {};
      if (dirty & /*chunks*/
      4) event_changes.chunk = /*chunk*/
      ctx[21];
      event.$set(event_changes);
    },
    i(local) {
      if (current) return;
      transition_in(event.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(event.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(first);
      }
      destroy_component(event, detaching);
    }
  };
}
function create_fragment$14(ctx) {
  let if_block_anchor;
  let current;
  let if_block = (
    /*chunks*/
    ctx[2].length && create_if_block$13(ctx)
  );
  return {
    c() {
      if (if_block) if_block.c();
      if_block_anchor = empty();
    },
    m(target, anchor) {
      if (if_block) if_block.m(target, anchor);
      insert(target, if_block_anchor, anchor);
      current = true;
    },
    p(ctx2, [dirty]) {
      if (
        /*chunks*/
        ctx2[2].length
      ) {
        if (if_block) {
          if_block.p(ctx2, dirty);
          if (dirty & /*chunks*/
          4) {
            transition_in(if_block, 1);
          }
        } else {
          if_block = create_if_block$13(ctx2);
          if_block.c();
          transition_in(if_block, 1);
          if_block.m(if_block_anchor.parentNode, if_block_anchor);
        }
      } else if (if_block) {
        group_outros();
        transition_out(if_block, 1, 1, () => {
          if_block = null;
        });
        check_outros();
      }
    },
    i(local) {
      if (current) return;
      transition_in(if_block);
      current = true;
    },
    o(local) {
      transition_out(if_block);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(if_block_anchor);
      }
      if (if_block) if_block.d(detaching);
    }
  };
}
function instance$14($$self, $$props, $$invalidate) {
  let $highlightedDates;
  let $_today;
  let $_events;
  let $theme;
  let $_interaction;
  let $_intlListDay;
  let $_intlListDaySide;
  let { date } = $$props;
  let { _events, _interaction, _intlListDay, _intlListDaySide, _today, highlightedDates, theme } = getContext("state");
  component_subscribe($$self, _events, (value) => $$invalidate(19, $_events = value));
  component_subscribe($$self, _interaction, (value) => $$invalidate(7, $_interaction = value));
  component_subscribe($$self, _intlListDay, (value) => $$invalidate(8, $_intlListDay = value));
  component_subscribe($$self, _intlListDaySide, (value) => $$invalidate(9, $_intlListDaySide = value));
  component_subscribe($$self, _today, (value) => $$invalidate(18, $_today = value));
  component_subscribe($$self, highlightedDates, (value) => $$invalidate(17, $highlightedDates = value));
  component_subscribe($$self, theme, (value) => $$invalidate(6, $theme = value));
  let el;
  let chunks;
  let isToday, highlight;
  let datetime;
  function div_binding($$value) {
    binding_callbacks[$$value ? "unshift" : "push"](() => {
      el = $$value;
      $$invalidate(1, el);
    });
  }
  $$self.$$set = ($$props2) => {
    if ("date" in $$props2) $$invalidate(0, date = $$props2.date);
  };
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*date, $_events, chunks*/
    524293) {
      {
        $$invalidate(2, chunks = []);
        let start = date;
        let end = addDay(cloneDate(date));
        for (let event of $_events) {
          if (!bgEvent(event.display) && eventIntersects(event, start, end)) {
            let chunk = createEventChunk(event, start, end);
            chunks.push(chunk);
          }
        }
        sortEventChunks(chunks);
      }
    }
    if ($$self.$$.dirty & /*date, $_today*/
    262145) {
      $$invalidate(3, isToday = datesEqual(date, $_today));
    }
    if ($$self.$$.dirty & /*$highlightedDates, date*/
    131073) {
      $$invalidate(4, highlight = $highlightedDates.some((d) => datesEqual(d, date)));
    }
    if ($$self.$$.dirty & /*date*/
    1) {
      $$invalidate(5, datetime = toISOString(date, 10));
    }
    if ($$self.$$.dirty & /*el, date*/
    3) {
      if (el) {
        setPayload(el, () => ({
          allDay: true,
          date,
          resource: void 0,
          dayEl: el
        }));
      }
    }
  };
  return [
    date,
    el,
    chunks,
    isToday,
    highlight,
    datetime,
    $theme,
    $_interaction,
    $_intlListDay,
    $_intlListDaySide,
    _events,
    _interaction,
    _intlListDay,
    _intlListDaySide,
    _today,
    highlightedDates,
    theme,
    $highlightedDates,
    $_today,
    $_events,
    div_binding
  ];
}
var Day4 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance$14, create_fragment$14, safe_not_equal, { date: 0 });
  }
};
function get_each_context4(ctx, list, i) {
  const child_ctx = ctx.slice();
  child_ctx[15] = list[i];
  return child_ctx;
}
function create_else_block2(ctx) {
  let each_1_anchor;
  let current;
  let each_value = ensure_array_like(
    /*$_viewDates*/
    ctx[1]
  );
  let each_blocks = [];
  for (let i = 0; i < each_value.length; i += 1) {
    each_blocks[i] = create_each_block4(get_each_context4(ctx, each_value, i));
  }
  const out = (i) => transition_out(each_blocks[i], 1, 1, () => {
    each_blocks[i] = null;
  });
  return {
    c() {
      for (let i = 0; i < each_blocks.length; i += 1) {
        each_blocks[i].c();
      }
      each_1_anchor = empty();
    },
    m(target, anchor) {
      for (let i = 0; i < each_blocks.length; i += 1) {
        if (each_blocks[i]) {
          each_blocks[i].m(target, anchor);
        }
      }
      insert(target, each_1_anchor, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      if (dirty & /*$_viewDates*/
      2) {
        each_value = ensure_array_like(
          /*$_viewDates*/
          ctx2[1]
        );
        let i;
        for (i = 0; i < each_value.length; i += 1) {
          const child_ctx = get_each_context4(ctx2, each_value, i);
          if (each_blocks[i]) {
            each_blocks[i].p(child_ctx, dirty);
            transition_in(each_blocks[i], 1);
          } else {
            each_blocks[i] = create_each_block4(child_ctx);
            each_blocks[i].c();
            transition_in(each_blocks[i], 1);
            each_blocks[i].m(each_1_anchor.parentNode, each_1_anchor);
          }
        }
        group_outros();
        for (i = each_value.length; i < each_blocks.length; i += 1) {
          out(i);
        }
        check_outros();
      }
    },
    i(local) {
      if (current) return;
      for (let i = 0; i < each_value.length; i += 1) {
        transition_in(each_blocks[i]);
      }
      current = true;
    },
    o(local) {
      each_blocks = each_blocks.filter(Boolean);
      for (let i = 0; i < each_blocks.length; i += 1) {
        transition_out(each_blocks[i]);
      }
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(each_1_anchor);
      }
      destroy_each(each_blocks, detaching);
    }
  };
}
function create_if_block4(ctx) {
  let div;
  let div_class_value;
  let setContent_action;
  let mounted;
  let dispose;
  return {
    c() {
      div = element("div");
      attr(div, "class", div_class_value = /*$theme*/
      ctx[3].noEvents);
    },
    m(target, anchor) {
      insert(target, div, anchor);
      if (!mounted) {
        dispose = [
          action_destroyer(setContent_action = setContent.call(
            null,
            div,
            /*content*/
            ctx[0]
          )),
          listen(
            div,
            "click",
            /*handleClick*/
            ctx[10]
          )
        ];
        mounted = true;
      }
    },
    p(ctx2, dirty) {
      if (dirty & /*$theme*/
      8 && div_class_value !== (div_class_value = /*$theme*/
      ctx2[3].noEvents)) {
        attr(div, "class", div_class_value);
      }
      if (setContent_action && is_function(setContent_action.update) && dirty & /*content*/
      1) setContent_action.update.call(
        null,
        /*content*/
        ctx2[0]
      );
    },
    i: noop,
    o: noop,
    d(detaching) {
      if (detaching) {
        detach(div);
      }
      mounted = false;
      run_all(dispose);
    }
  };
}
function create_each_block4(ctx) {
  let day;
  let current;
  day = new Day4({ props: { date: (
    /*date*/
    ctx[15]
  ) } });
  return {
    c() {
      create_component(day.$$.fragment);
    },
    m(target, anchor) {
      mount_component(day, target, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      const day_changes = {};
      if (dirty & /*$_viewDates*/
      2) day_changes.date = /*date*/
      ctx2[15];
      day.$set(day_changes);
    },
    i(local) {
      if (current) return;
      transition_in(day.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(day.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(day, detaching);
    }
  };
}
function create_default_slot3(ctx) {
  let current_block_type_index;
  let if_block;
  let if_block_anchor;
  let current;
  const if_block_creators = [create_if_block4, create_else_block2];
  const if_blocks = [];
  function select_block_type(ctx2, dirty) {
    if (
      /*noEvents*/
      ctx2[2]
    ) return 0;
    return 1;
  }
  current_block_type_index = select_block_type(ctx);
  if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx);
  return {
    c() {
      if_block.c();
      if_block_anchor = empty();
    },
    m(target, anchor) {
      if_blocks[current_block_type_index].m(target, anchor);
      insert(target, if_block_anchor, anchor);
      current = true;
    },
    p(ctx2, dirty) {
      let previous_block_index = current_block_type_index;
      current_block_type_index = select_block_type(ctx2);
      if (current_block_type_index === previous_block_index) {
        if_blocks[current_block_type_index].p(ctx2, dirty);
      } else {
        group_outros();
        transition_out(if_blocks[previous_block_index], 1, 1, () => {
          if_blocks[previous_block_index] = null;
        });
        check_outros();
        if_block = if_blocks[current_block_type_index];
        if (!if_block) {
          if_block = if_blocks[current_block_type_index] = if_block_creators[current_block_type_index](ctx2);
          if_block.c();
        } else {
          if_block.p(ctx2, dirty);
        }
        transition_in(if_block, 1);
        if_block.m(if_block_anchor.parentNode, if_block_anchor);
      }
    },
    i(local) {
      if (current) return;
      transition_in(if_block);
      current = true;
    },
    o(local) {
      transition_out(if_block);
      current = false;
    },
    d(detaching) {
      if (detaching) {
        detach(if_block_anchor);
      }
      if_blocks[current_block_type_index].d(detaching);
    }
  };
}
function create_fragment4(ctx) {
  let body;
  let current;
  body = new Body3({
    props: {
      $$slots: { default: [create_default_slot3] },
      $$scope: { ctx }
    }
  });
  return {
    c() {
      create_component(body.$$.fragment);
    },
    m(target, anchor) {
      mount_component(body, target, anchor);
      current = true;
    },
    p(ctx2, [dirty]) {
      const body_changes = {};
      if (dirty & /*$$scope, $theme, content, noEvents, $_viewDates*/
      262159) {
        body_changes.$$scope = { dirty, ctx: ctx2 };
      }
      body.$set(body_changes);
    },
    i(local) {
      if (current) return;
      transition_in(body.$$.fragment, local);
      current = true;
    },
    o(local) {
      transition_out(body.$$.fragment, local);
      current = false;
    },
    d(detaching) {
      destroy_component(body, detaching);
    }
  };
}
function instance4($$self, $$props, $$invalidate) {
  let $_view;
  let $noEventsClick;
  let $noEventsContent;
  let $_events;
  let $_viewDates;
  let $theme;
  let { _events, _view, _viewDates, noEventsClick, noEventsContent, theme } = getContext("state");
  component_subscribe($$self, _events, (value) => $$invalidate(12, $_events = value));
  component_subscribe($$self, _view, (value) => $$invalidate(13, $_view = value));
  component_subscribe($$self, _viewDates, (value) => $$invalidate(1, $_viewDates = value));
  component_subscribe($$self, noEventsClick, (value) => $$invalidate(14, $noEventsClick = value));
  component_subscribe($$self, noEventsContent, (value) => $$invalidate(11, $noEventsContent = value));
  component_subscribe($$self, theme, (value) => $$invalidate(3, $theme = value));
  let noEvents, content;
  function handleClick(jsEvent) {
    if (is_function($noEventsClick)) {
      $noEventsClick({
        jsEvent,
        view: toViewWithLocalDates($_view)
      });
    }
  }
  $$self.$$.update = () => {
    if ($$self.$$.dirty & /*$_viewDates, $_events*/
    4098) {
      {
        $$invalidate(2, noEvents = true);
        if ($_viewDates.length) {
          let start = $_viewDates[0];
          let end = addDay(cloneDate($_viewDates[$_viewDates.length - 1]));
          for (let event of $_events) {
            if (!bgEvent(event.display) && event.start < end && event.end > start) {
              $$invalidate(2, noEvents = false);
              break;
            }
          }
        }
      }
    }
    if ($$self.$$.dirty & /*$noEventsContent, content*/
    2049) {
      {
        $$invalidate(0, content = is_function($noEventsContent) ? $noEventsContent() : $noEventsContent);
        if (typeof content === "string") {
          $$invalidate(0, content = { html: content });
        }
      }
    }
  };
  return [
    content,
    $_viewDates,
    noEvents,
    $theme,
    _events,
    _view,
    _viewDates,
    noEventsClick,
    noEventsContent,
    theme,
    handleClick,
    $noEventsContent,
    $_events
  ];
}
var View3 = class extends SvelteComponent {
  constructor(options) {
    super();
    init(this, options, instance4, create_fragment4, safe_not_equal, {});
  }
};
var index3 = {
  createOptions(options) {
    options.buttonText.listDay = "list";
    options.buttonText.listWeek = "list";
    options.buttonText.listMonth = "list";
    options.buttonText.listYear = "list";
    options.listDayFormat = { weekday: "long" };
    options.listDaySideFormat = { year: "numeric", month: "long", day: "numeric" };
    options.noEventsClick = void 0;
    options.noEventsContent = "No events";
    options.theme.daySide = "ec-day-side";
    options.theme.eventTag = "ec-event-tag";
    options.theme.noEvents = "ec-no-events";
    options.view = "listWeek";
    options.views.listDay = {
      buttonText: btnTextDay,
      component: View3,
      duration: { days: 1 },
      theme: themeView("ec-list ec-day-view")
    };
    options.views.listWeek = {
      buttonText: btnTextWeek,
      component: View3,
      duration: { weeks: 1 },
      theme: themeView("ec-list ec-week-view")
    };
    options.views.listMonth = {
      buttonText: btnTextMonth,
      component: View3,
      duration: { months: 1 },
      theme: themeView("ec-list ec-month-view")
    };
    options.views.listYear = {
      buttonText: btnTextYear,
      component: View3,
      duration: { years: 1 },
      theme: themeView("ec-list ec-year-view")
    };
  },
  createStores(state) {
    state._intlListDay = intl(state.locale, state.listDayFormat);
    state._intlListDaySide = intl(state.locale, state.listDaySideFormat);
  }
};

// Resources/Private/TypeScript/calendar.ts
document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("xima-calendar-mount");
  if (!container) {
    return;
  }
  const ajaxUrl = container.dataset.ajaxUrl ?? "";
  const getSelectedUids = () => Array.from(document.querySelectorAll(".xima-cal-filter__checkbox:checked")).map((cb) => parseInt(cb.value, 10));
  const ec = new Calendar({
    target: container,
    props: {
      plugins: [index, index2, index3],
      options: {
        view: "dayGridMonth",
        headerToolbar: {
          start: "prev,next today",
          center: "title",
          end: "dayGridMonth,timeGridWeek,listMonth"
        },
        eventSources: [
          {
            url: ajaxUrl
          }
        ]
      }
    }
  });
  document.querySelectorAll(".xima-cal-filter__checkbox").forEach((cb) => {
    cb.addEventListener("change", () => ec.refetchEvents());
  });
});
//# sourceMappingURL=calendar.js.map
