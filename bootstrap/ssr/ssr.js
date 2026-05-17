import axios from "axios";
import { createInertiaApp } from "@inertiajs/vue3";
import createServer from "@inertiajs/vue3/server";
import { renderToString } from "@vue/server-renderer";
import { Comment, Fragment, Teleport, Text, Transition, TransitionGroup, camelize, capitalize, cloneVNode, computed, createSSRApp, createTextVNode, createVNode, defineComponent, effectScope, getCurrentInstance, h, inject, isRef, isVNode, markRaw, mergeProps, nextTick, onActivated, onBeforeMount, onBeforeUnmount, onBeforeUpdate, onDeactivated, onMounted, onScopeDispose, onUnmounted, onUpdated, provide, reactive, readonly, ref, render, resolveComponent, resolveDirective, resolveDynamicComponent, shallowRef, toDisplayString, toRaw, toRef, toRefs, toValue, unref, useId, vModelText, vShow, warn, watch, watchEffect, withDirectives, withModifiers } from "vue";
import { ZiggyVue } from "ziggy-js";
//#region \0rolldown/runtime.js
var __defProp = Object.defineProperty;
var __exportAll = (all, no_symbols) => {
	let target = {};
	for (var name in all) __defProp(target, name, {
		get: all[name],
		enumerable: true
	});
	if (!no_symbols) __defProp(target, Symbol.toStringTag, { value: "Module" });
	return target;
};
//#endregion
//#region resources/js/bootstrap.js
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
if (typeof window !== "undefined") window.axios = axios;
if (typeof window !== "undefined") window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
//#endregion
//#region node_modules/laravel-vite-plugin/inertia-helpers/index.js
async function resolvePageComponent(path, pages) {
	for (const p of Array.isArray(path) ? path : [path]) {
		const page = pages[p];
		if (typeof page === "undefined") continue;
		return typeof page === "function" ? page() : page;
	}
	throw new Error(`Page not found: ${path}`);
}
//#endregion
//#region node_modules/vuetify/lib/composables/toggleScope.js
function useToggleScope(source, fn) {
	let scope;
	function start() {
		scope = effectScope();
		scope.run(() => fn.length ? fn(() => {
			scope?.stop();
			start();
		}) : fn());
	}
	watch(source, (active) => {
		if (active && !scope) start();
		else if (!active) {
			scope?.stop();
			scope = void 0;
		}
	}, { immediate: true });
	onScopeDispose(() => {
		scope?.stop();
	});
}
//#endregion
//#region node_modules/vuetify/lib/util/globals.js
var IN_BROWSER = typeof window !== "undefined";
var SUPPORTS_INTERSECTION = IN_BROWSER && "IntersectionObserver" in window;
var SUPPORTS_TOUCH = IN_BROWSER && ("ontouchstart" in window || window.navigator.maxTouchPoints > 0);
var SUPPORTS_EYE_DROPPER = IN_BROWSER && "EyeDropper" in window;
//#endregion
//#region node_modules/vuetify/lib/util/helpers.js
function _classPrivateFieldInitSpec(e, t, a) {
	_checkPrivateRedeclaration(e, t), t.set(e, a);
}
function _checkPrivateRedeclaration(e, t) {
	if (t.has(e)) throw new TypeError("Cannot initialize the same private elements twice on an object");
}
function _classPrivateFieldSet(s, a, r) {
	return s.set(_assertClassBrand(s, a), r), r;
}
function _classPrivateFieldGet(s, a) {
	return s.get(_assertClassBrand(s, a));
}
function _assertClassBrand(e, t, n) {
	if ("function" == typeof e ? e === t : e.has(t)) return arguments.length < 3 ? t : n;
	throw new TypeError("Private element is not present on this object");
}
function getNestedValue(obj, path, fallback) {
	const last = path.length - 1;
	if (last < 0) return obj === void 0 ? fallback : obj;
	for (let i = 0; i < last; i++) {
		if (obj == null) return fallback;
		obj = obj[path[i]];
	}
	if (obj == null) return fallback;
	return obj[path[last]] === void 0 ? fallback : obj[path[last]];
}
function deepEqual(a, b) {
	if (a === b) return true;
	if (a instanceof Date && b instanceof Date && a.getTime() !== b.getTime()) return false;
	if (a !== Object(a) || b !== Object(b)) return false;
	const props = Object.keys(a);
	if (props.length !== Object.keys(b).length) return false;
	return props.every((p) => deepEqual(a[p], b[p]));
}
function getObjectValueByPath(obj, path, fallback) {
	if (obj == null || !path || typeof path !== "string") return fallback;
	if (obj[path] !== void 0) return obj[path];
	path = path.replace(/\[(\w+)\]/g, ".$1");
	path = path.replace(/^\./, "");
	return getNestedValue(obj, path.split("."), fallback);
}
function getPropertyFromItem(item, property, fallback) {
	if (property === true) return item === void 0 ? fallback : item;
	if (property == null || typeof property === "boolean") return fallback;
	if (item !== Object(item)) {
		if (typeof property !== "function") return fallback;
		const value = property(item, fallback);
		return typeof value === "undefined" ? fallback : value;
	}
	if (typeof property === "string") return getObjectValueByPath(item, property, fallback);
	if (Array.isArray(property)) return getNestedValue(item, property, fallback);
	if (typeof property !== "function") return fallback;
	const value = property(item, fallback);
	return typeof value === "undefined" ? fallback : value;
}
function createRange(length) {
	let start = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : 0;
	return Array.from({ length }, (v, k) => start + k);
}
function convertToUnit(str) {
	let unit = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : "px";
	if (str == null || str === "") return;
	const num = Number(str);
	if (isNaN(num)) return String(str);
	else if (!isFinite(num)) return;
	else return `${num}${unit}`;
}
function isObject(obj) {
	return obj !== null && typeof obj === "object" && !Array.isArray(obj);
}
function isPlainObject(obj) {
	let proto;
	return obj !== null && typeof obj === "object" && ((proto = Object.getPrototypeOf(obj)) === Object.prototype || proto === null);
}
function refElement(obj) {
	if (obj && "$el" in obj) {
		const el = obj.$el;
		if (el?.nodeType === Node.TEXT_NODE) return el.nextElementSibling;
		return el;
	}
	return obj;
}
var keyCodes = Object.freeze({
	enter: 13,
	tab: 9,
	delete: 46,
	esc: 27,
	space: 32,
	up: 38,
	down: 40,
	left: 37,
	right: 39,
	end: 35,
	home: 36,
	del: 46,
	backspace: 8,
	insert: 45,
	pageup: 33,
	pagedown: 34,
	shift: 16
});
var keyValues = Object.freeze({
	enter: "Enter",
	tab: "Tab",
	delete: "Delete",
	esc: "Escape",
	space: "Space",
	up: "ArrowUp",
	down: "ArrowDown",
	left: "ArrowLeft",
	right: "ArrowRight",
	end: "End",
	home: "Home",
	del: "Delete",
	backspace: "Backspace",
	insert: "Insert",
	pageup: "PageUp",
	pagedown: "PageDown",
	shift: "Shift"
});
function keys(o) {
	return Object.keys(o);
}
function has(obj, key) {
	return key.every((k) => obj.hasOwnProperty(k));
}
function pick(obj, paths) {
	const found = {};
	for (const key of paths) if (Object.prototype.hasOwnProperty.call(obj, key)) found[key] = obj[key];
	return found;
}
function pickWithRest(obj, paths, exclude) {
	const found = Object.create(null);
	const rest = Object.create(null);
	for (const key in obj) if (paths.some((path) => path instanceof RegExp ? path.test(key) : path === key) && !exclude?.some((path) => path === key)) found[key] = obj[key];
	else rest[key] = obj[key];
	return [found, rest];
}
function omit(obj, exclude) {
	const clone = { ...obj };
	exclude.forEach((prop) => delete clone[prop]);
	return clone;
}
var onRE = /^on[^a-z]/;
var isOn = (key) => onRE.test(key);
var bubblingEvents = [
	"onAfterscriptexecute",
	"onAnimationcancel",
	"onAnimationend",
	"onAnimationiteration",
	"onAnimationstart",
	"onAuxclick",
	"onBeforeinput",
	"onBeforescriptexecute",
	"onChange",
	"onClick",
	"onCompositionend",
	"onCompositionstart",
	"onCompositionupdate",
	"onContextmenu",
	"onCopy",
	"onCut",
	"onDblclick",
	"onFocusin",
	"onFocusout",
	"onFullscreenchange",
	"onFullscreenerror",
	"onGesturechange",
	"onGestureend",
	"onGesturestart",
	"onGotpointercapture",
	"onInput",
	"onKeydown",
	"onKeypress",
	"onKeyup",
	"onLostpointercapture",
	"onMousedown",
	"onMousemove",
	"onMouseout",
	"onMouseover",
	"onMouseup",
	"onMousewheel",
	"onPaste",
	"onPointercancel",
	"onPointerdown",
	"onPointerenter",
	"onPointerleave",
	"onPointermove",
	"onPointerout",
	"onPointerover",
	"onPointerup",
	"onReset",
	"onSelect",
	"onSubmit",
	"onTouchcancel",
	"onTouchend",
	"onTouchmove",
	"onTouchstart",
	"onTransitioncancel",
	"onTransitionend",
	"onTransitionrun",
	"onTransitionstart",
	"onWheel"
];
var compositionIgnoreKeys = [
	"ArrowUp",
	"ArrowDown",
	"ArrowRight",
	"ArrowLeft",
	"Enter",
	"Escape",
	"Tab",
	" "
];
function isComposingIgnoreKey(e) {
	return e.isComposing && compositionIgnoreKeys.includes(e.key);
}
/**
* Filter attributes that should be applied to
* the root element of an input component. Remaining
* attributes should be passed to the <input> element inside.
*/
function filterInputAttrs(attrs) {
	const [events, props] = pickWithRest(attrs, [onRE]);
	const inputEvents = omit(events, bubblingEvents);
	const [rootAttrs, inputAttrs] = pickWithRest(props, [
		"class",
		"style",
		"id",
		/^data-/
	]);
	Object.assign(rootAttrs, events);
	Object.assign(inputAttrs, inputEvents);
	return [rootAttrs, inputAttrs];
}
function wrapInArray(v) {
	return v == null ? [] : Array.isArray(v) ? v : [v];
}
function debounce(fn, delay) {
	let timeoutId = 0;
	const wrap = function() {
		for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
		clearTimeout(timeoutId);
		timeoutId = setTimeout(() => fn(...args), unref(delay));
	};
	wrap.clear = () => {
		clearTimeout(timeoutId);
	};
	wrap.immediate = fn;
	return wrap;
}
function clamp(value) {
	let min = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : 0;
	let max = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : 1;
	return Math.max(min, Math.min(max, value));
}
function getDecimals(value) {
	const trimmedStr = value.toString().trim();
	return trimmedStr.includes(".") ? trimmedStr.length - trimmedStr.indexOf(".") - 1 : 0;
}
function padEnd(str, length) {
	return str + (arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : "0").repeat(Math.max(0, length - str.length));
}
function padStart(str, length) {
	return (arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : "0").repeat(Math.max(0, length - str.length)) + str;
}
function chunk(str) {
	let size = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : 1;
	const chunked = [];
	let index = 0;
	while (index < str.length) {
		chunked.push(str.substr(index, size));
		index += size;
	}
	return chunked;
}
function humanReadableFileSize(bytes) {
	let base = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : 1e3;
	if (bytes < base) return `${bytes} B`;
	const prefix = base === 1024 ? [
		"Ki",
		"Mi",
		"Gi"
	] : [
		"k",
		"M",
		"G"
	];
	let unit = -1;
	while (Math.abs(bytes) >= base && unit < prefix.length - 1) {
		bytes /= base;
		++unit;
	}
	return `${bytes.toFixed(1)} ${prefix[unit]}B`;
}
function mergeDeep() {
	let source = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : {};
	let target = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : {};
	let arrayFn = arguments.length > 2 ? arguments[2] : void 0;
	const out = {};
	for (const key in source) out[key] = source[key];
	for (const key in target) {
		const sourceProperty = source[key];
		const targetProperty = target[key];
		if (isPlainObject(sourceProperty) && isPlainObject(targetProperty)) {
			out[key] = mergeDeep(sourceProperty, targetProperty, arrayFn);
			continue;
		}
		if (arrayFn && Array.isArray(sourceProperty) && Array.isArray(targetProperty)) {
			out[key] = arrayFn(sourceProperty, targetProperty);
			continue;
		}
		out[key] = targetProperty;
	}
	return out;
}
function flattenFragments(nodes) {
	return nodes.map((node) => {
		if (node.type === Fragment) return flattenFragments(node.children);
		else return node;
	}).flat();
}
function toKebabCase() {
	let str = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : "";
	if (toKebabCase.cache.has(str)) return toKebabCase.cache.get(str);
	const kebab = str.replace(/[^a-z]/gi, "-").replace(/\B([A-Z])/g, "-$1").toLowerCase();
	toKebabCase.cache.set(str, kebab);
	return kebab;
}
toKebabCase.cache = /* @__PURE__ */ new Map();
function findChildrenWithProvide(key, vnode) {
	if (!vnode || typeof vnode !== "object") return [];
	if (Array.isArray(vnode)) return vnode.map((child) => findChildrenWithProvide(key, child)).flat(1);
	else if (vnode.suspense) return findChildrenWithProvide(key, vnode.ssContent);
	else if (Array.isArray(vnode.children)) return vnode.children.map((child) => findChildrenWithProvide(key, child)).flat(1);
	else if (vnode.component) {
		if (Object.getOwnPropertySymbols(vnode.component.provides).includes(key)) return [vnode.component];
		else if (vnode.component.subTree) return findChildrenWithProvide(key, vnode.component.subTree).flat(1);
	}
	return [];
}
var _arr = /* @__PURE__ */ new WeakMap();
var _pointer = /* @__PURE__ */ new WeakMap();
var CircularBuffer = class {
	constructor(size) {
		_classPrivateFieldInitSpec(this, _arr, []);
		_classPrivateFieldInitSpec(this, _pointer, 0);
		this.size = size;
	}
	get isFull() {
		return _classPrivateFieldGet(_arr, this).length === this.size;
	}
	push(val) {
		_classPrivateFieldGet(_arr, this)[_classPrivateFieldGet(_pointer, this)] = val;
		_classPrivateFieldSet(_pointer, this, (_classPrivateFieldGet(_pointer, this) + 1) % this.size);
	}
	values() {
		return _classPrivateFieldGet(_arr, this).slice(_classPrivateFieldGet(_pointer, this)).concat(_classPrivateFieldGet(_arr, this).slice(0, _classPrivateFieldGet(_pointer, this)));
	}
	clear() {
		_classPrivateFieldGet(_arr, this).length = 0;
		_classPrivateFieldSet(_pointer, this, 0);
	}
};
function getEventCoordinates(e) {
	if ("touches" in e) return {
		clientX: e.touches[0].clientX,
		clientY: e.touches[0].clientY
	};
	return {
		clientX: e.clientX,
		clientY: e.clientY
	};
}
/**
* Convert a computed ref to a record of refs.
* The getter function must always return an object with the same keys.
*/
function destructComputed(getter) {
	const refs = reactive({});
	watchEffect(() => {
		const base = getter();
		for (const key in base) refs[key] = base[key];
	}, { flush: "sync" });
	const obj = {};
	for (const key in refs) obj[key] = toRef(() => refs[key]);
	return obj;
}
/** Array.includes but value can be any type */
function includes(arr, val) {
	return arr.includes(val);
}
function eventName(propName) {
	return propName[2].toLowerCase() + propName.slice(3);
}
var EventProp = () => [Function, Array];
function hasEvent(props, name) {
	name = "on" + capitalize(name);
	return !!(props[name] || props[`${name}Once`] || props[`${name}Capture`] || props[`${name}OnceCapture`] || props[`${name}CaptureOnce`]);
}
function callEvent(handler) {
	for (var _len2 = arguments.length, args = new Array(_len2 > 1 ? _len2 - 1 : 0), _key2 = 1; _key2 < _len2; _key2++) args[_key2 - 1] = arguments[_key2];
	if (Array.isArray(handler)) for (const h of handler) h(...args);
	else if (typeof handler === "function") handler(...args);
}
function focusableChildren(el) {
	let filterByTabIndex = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : true;
	const targets = [
		"button",
		"[href]",
		"input:not([type=\"hidden\"])",
		"select",
		"textarea",
		"[tabindex]"
	].map((s) => `${s}${filterByTabIndex ? ":not([tabindex=\"-1\"])" : ""}:not([disabled])`).join(", ");
	return [...el.querySelectorAll(targets)];
}
function getNextElement(elements, location, condition) {
	let _el;
	let idx = elements.indexOf(document.activeElement);
	const inc = location === "next" ? 1 : -1;
	do {
		idx += inc;
		_el = elements[idx];
	} while ((!_el || _el.offsetParent == null || !(condition?.(_el) ?? true)) && idx < elements.length && idx >= 0);
	return _el;
}
function focusChild(el, location) {
	const focusable = focusableChildren(el);
	if (!location) {
		if (el === document.activeElement || !el.contains(document.activeElement)) focusable[0]?.focus();
	} else if (location === "first") focusable[0]?.focus();
	else if (location === "last") focusable.at(-1)?.focus();
	else if (typeof location === "number") focusable[location]?.focus();
	else {
		const _el = getNextElement(focusable, location);
		if (_el) _el.focus();
		else focusChild(el, location === "next" ? "first" : "last");
	}
}
function isEmpty(val) {
	return val === null || val === void 0 || typeof val === "string" && val.trim() === "";
}
function noop() {}
/** Returns null if the selector is not supported or we can't check */
function matchesSelector(el, selector) {
	if (!(IN_BROWSER && typeof CSS !== "undefined" && typeof CSS.supports !== "undefined" && CSS.supports(`selector(${selector})`))) return null;
	try {
		return !!el && el.matches(selector);
	} catch (err) {
		return null;
	}
}
function ensureValidVNode(vnodes) {
	return vnodes.some((child) => {
		if (!isVNode(child)) return true;
		if (child.type === Comment) return false;
		return child.type !== Fragment || ensureValidVNode(child.children);
	}) ? vnodes : null;
}
function defer(timeout, cb) {
	if (!IN_BROWSER || timeout === 0) {
		cb();
		return () => {};
	}
	const timeoutId = window.setTimeout(cb, timeout);
	return () => window.clearTimeout(timeoutId);
}
function isClickInsideElement(event, targetDiv) {
	const mouseX = event.clientX;
	const mouseY = event.clientY;
	const divRect = targetDiv.getBoundingClientRect();
	const divLeft = divRect.left;
	const divTop = divRect.top;
	const divRight = divRect.right;
	const divBottom = divRect.bottom;
	return mouseX >= divLeft && mouseX <= divRight && mouseY >= divTop && mouseY <= divBottom;
}
function templateRef() {
	const el = shallowRef();
	const fn = (target) => {
		el.value = target;
	};
	Object.defineProperty(fn, "value", {
		enumerable: true,
		get: () => el.value,
		set: (val) => el.value = val
	});
	Object.defineProperty(fn, "el", {
		enumerable: true,
		get: () => refElement(el.value)
	});
	return fn;
}
function checkPrintable(e) {
	const isPrintableChar = e.key.length === 1;
	const noModifier = !e.ctrlKey && !e.metaKey && !e.altKey;
	return isPrintableChar && noModifier;
}
function isPrimitive(value) {
	return typeof value === "string" || typeof value === "number" || typeof value === "boolean" || typeof value === "bigint";
}
//#endregion
//#region node_modules/vuetify/lib/util/anchor.js
var block = ["top", "bottom"];
var inline = [
	"start",
	"end",
	"left",
	"right"
];
/** Parse a raw anchor string into an object */
function parseAnchor(anchor, isRtl) {
	let [side, align] = anchor.split(" ");
	if (!align) align = includes(block, side) ? "start" : includes(inline, side) ? "top" : "center";
	return {
		side: toPhysical(side, isRtl),
		align: toPhysical(align, isRtl)
	};
}
function toPhysical(str, isRtl) {
	if (str === "start") return isRtl ? "right" : "left";
	if (str === "end") return isRtl ? "left" : "right";
	return str;
}
function flipSide(anchor) {
	return {
		side: {
			center: "center",
			top: "bottom",
			bottom: "top",
			left: "right",
			right: "left"
		}[anchor.side],
		align: anchor.align
	};
}
function flipAlign(anchor) {
	return {
		side: anchor.side,
		align: {
			center: "center",
			top: "bottom",
			bottom: "top",
			left: "right",
			right: "left"
		}[anchor.align]
	};
}
function flipCorner(anchor) {
	return {
		side: anchor.align,
		align: anchor.side
	};
}
function getAxis(anchor) {
	return includes(block, anchor.side) ? "y" : "x";
}
//#endregion
//#region node_modules/vuetify/lib/util/box.js
var Box = class {
	constructor(_ref) {
		let { x, y, width, height } = _ref;
		this.x = x;
		this.y = y;
		this.width = width;
		this.height = height;
	}
	get top() {
		return this.y;
	}
	get bottom() {
		return this.y + this.height;
	}
	get left() {
		return this.x;
	}
	get right() {
		return this.x + this.width;
	}
};
function getOverflow(a, b) {
	return {
		x: {
			before: Math.max(0, b.left - a.left),
			after: Math.max(0, a.right - b.right)
		},
		y: {
			before: Math.max(0, b.top - a.top),
			after: Math.max(0, a.bottom - b.bottom)
		}
	};
}
function getTargetBox(target) {
	if (Array.isArray(target)) return new Box({
		x: target[0],
		y: target[1],
		width: 0,
		height: 0
	});
	else return target.getBoundingClientRect();
}
//#endregion
//#region node_modules/vuetify/lib/util/animation.js
/** @see https://stackoverflow.com/a/57876601/2074736 */
function nullifyTransforms(el) {
	const rect = el.getBoundingClientRect();
	const style = getComputedStyle(el);
	const tx = style.transform;
	if (tx) {
		let ta, sx, sy, dx, dy;
		if (tx.startsWith("matrix3d(")) {
			ta = tx.slice(9, -1).split(/, /);
			sx = Number(ta[0]);
			sy = Number(ta[5]);
			dx = Number(ta[12]);
			dy = Number(ta[13]);
		} else if (tx.startsWith("matrix(")) {
			ta = tx.slice(7, -1).split(/, /);
			sx = Number(ta[0]);
			sy = Number(ta[3]);
			dx = Number(ta[4]);
			dy = Number(ta[5]);
		} else return new Box(rect);
		const to = style.transformOrigin;
		return new Box({
			x: rect.x - dx - (1 - sx) * parseFloat(to),
			y: rect.y - dy - (1 - sy) * parseFloat(to.slice(to.indexOf(" ") + 1)),
			width: sx ? rect.width / sx : el.offsetWidth + 1,
			height: sy ? rect.height / sy : el.offsetHeight + 1
		});
	} else return new Box(rect);
}
function animate(el, keyframes, options) {
	if (typeof el.animate === "undefined") return { finished: Promise.resolve() };
	let animation;
	try {
		animation = el.animate(keyframes, options);
	} catch (err) {
		return { finished: Promise.resolve() };
	}
	if (typeof animation.finished === "undefined") animation.finished = new Promise((resolve) => {
		animation.onfinish = () => {
			resolve(animation);
		};
	});
	return animation;
}
//#endregion
//#region node_modules/vuetify/lib/util/bindProps.js
var handlers = /* @__PURE__ */ new WeakMap();
function bindProps(el, props) {
	Object.keys(props).forEach((k) => {
		if (isOn(k)) {
			const name = eventName(k);
			const handler = handlers.get(el);
			if (props[k] == null) handler?.forEach((v) => {
				const [n, fn] = v;
				if (n === name) {
					el.removeEventListener(name, fn);
					handler.delete(v);
				}
			});
			else if (!handler || ![...handler]?.some((v) => v[0] === name && v[1] === props[k])) {
				el.addEventListener(name, props[k]);
				const _handler = handler || /* @__PURE__ */ new Set();
				_handler.add([name, props[k]]);
				if (!handlers.has(el)) handlers.set(el, _handler);
			}
		} else if (props[k] == null) el.removeAttribute(k);
		else el.setAttribute(k, props[k]);
	});
}
function unbindProps(el, props) {
	Object.keys(props).forEach((k) => {
		if (isOn(k)) {
			const name = eventName(k);
			const handler = handlers.get(el);
			handler?.forEach((v) => {
				const [n, fn] = v;
				if (n === name) {
					el.removeEventListener(name, fn);
					handler.delete(v);
				}
			});
		} else el.removeAttribute(k);
	});
}
//#endregion
//#region node_modules/vuetify/lib/util/color/APCA.js
/**
* WCAG 3.0 APCA perceptual contrast algorithm from https://github.com/Myndex/SAPC-APCA
* @licence https://www.w3.org/Consortium/Legal/2015/copyright-software-and-document
* @see https://www.w3.org/WAI/GL/task-forces/silver/wiki/Visual_Contrast_of_Text_Subgroup
*/
var mainTRC = 2.4;
var Rco = .2126729;
var Gco = .7151522;
var Bco = .072175;
var normBG = .55;
var normTXT = .58;
var revTXT = .57;
var revBG = .62;
var blkThrs = .03;
var blkClmp = 1.45;
var deltaYmin = 5e-4;
var scaleBoW = 1.25;
var scaleWoB = 1.25;
var loConThresh = .078;
var loConFactor = 12.82051282051282;
var loConOffset = .06;
var loClip = .001;
function APCAcontrast(text, background) {
	const Rtxt = (text.r / 255) ** mainTRC;
	const Gtxt = (text.g / 255) ** mainTRC;
	const Btxt = (text.b / 255) ** mainTRC;
	const Rbg = (background.r / 255) ** mainTRC;
	const Gbg = (background.g / 255) ** mainTRC;
	const Bbg = (background.b / 255) ** mainTRC;
	let Ytxt = Rtxt * Rco + Gtxt * Gco + Btxt * Bco;
	let Ybg = Rbg * Rco + Gbg * Gco + Bbg * Bco;
	if (Ytxt <= blkThrs) Ytxt += (blkThrs - Ytxt) ** blkClmp;
	if (Ybg <= blkThrs) Ybg += (blkThrs - Ybg) ** blkClmp;
	if (Math.abs(Ybg - Ytxt) < deltaYmin) return 0;
	let outputContrast;
	if (Ybg > Ytxt) {
		const SAPC = (Ybg ** normBG - Ytxt ** normTXT) * scaleBoW;
		outputContrast = SAPC < loClip ? 0 : SAPC < loConThresh ? SAPC - SAPC * loConFactor * loConOffset : SAPC - loConOffset;
	} else {
		const SAPC = (Ybg ** revBG - Ytxt ** revTXT) * scaleWoB;
		outputContrast = SAPC > -loClip ? 0 : SAPC > -loConThresh ? SAPC - SAPC * loConFactor * loConOffset : SAPC + loConOffset;
	}
	return outputContrast * 100;
}
//#endregion
//#region node_modules/vuetify/lib/util/console.js
function consoleWarn(message) {
	warn(`Vuetify: ${message}`);
}
function consoleError(message) {
	warn(`Vuetify error: ${message}`);
}
function deprecate(original, replacement) {
	replacement = Array.isArray(replacement) ? replacement.slice(0, -1).map((s) => `'${s}'`).join(", ") + ` or '${replacement.at(-1)}'` : `'${replacement}'`;
	warn(`[Vuetify UPGRADE] '${original}' is deprecated, use ${replacement} instead.`);
}
//#endregion
//#region node_modules/vuetify/lib/util/color/transformCIELAB.js
var delta = .20689655172413793;
var cielabForwardTransform = (t) => t > delta ** 3 ? Math.cbrt(t) : t / (3 * delta ** 2) + 4 / 29;
var cielabReverseTransform = (t) => t > delta ? t ** 3 : 3 * delta ** 2 * (t - 4 / 29);
function fromXYZ$1(xyz) {
	const transform = cielabForwardTransform;
	const transformedY = transform(xyz[1]);
	return [
		116 * transformedY - 16,
		500 * (transform(xyz[0] / .95047) - transformedY),
		200 * (transformedY - transform(xyz[2] / 1.08883))
	];
}
function toXYZ$1(lab) {
	const transform = cielabReverseTransform;
	const Ln = (lab[0] + 16) / 116;
	return [
		transform(Ln + lab[1] / 500) * .95047,
		transform(Ln),
		transform(Ln - lab[2] / 200) * 1.08883
	];
}
//#endregion
//#region node_modules/vuetify/lib/util/color/transformSRGB.js
var srgbForwardMatrix = [
	[
		3.2406,
		-1.5372,
		-.4986
	],
	[
		-.9689,
		1.8758,
		.0415
	],
	[
		.0557,
		-.204,
		1.057
	]
];
var srgbForwardTransform = (C) => C <= .0031308 ? C * 12.92 : 1.055 * C ** (1 / 2.4) - .055;
var srgbReverseMatrix = [
	[
		.4124,
		.3576,
		.1805
	],
	[
		.2126,
		.7152,
		.0722
	],
	[
		.0193,
		.1192,
		.9505
	]
];
var srgbReverseTransform = (C) => C <= .04045 ? C / 12.92 : ((C + .055) / 1.055) ** 2.4;
function fromXYZ(xyz) {
	const rgb = Array(3);
	const transform = srgbForwardTransform;
	const matrix = srgbForwardMatrix;
	for (let i = 0; i < 3; ++i) rgb[i] = Math.round(clamp(transform(matrix[i][0] * xyz[0] + matrix[i][1] * xyz[1] + matrix[i][2] * xyz[2])) * 255);
	return {
		r: rgb[0],
		g: rgb[1],
		b: rgb[2]
	};
}
function toXYZ(_ref) {
	let { r, g, b } = _ref;
	const xyz = [
		0,
		0,
		0
	];
	const transform = srgbReverseTransform;
	const matrix = srgbReverseMatrix;
	r = transform(r / 255);
	g = transform(g / 255);
	b = transform(b / 255);
	for (let i = 0; i < 3; ++i) xyz[i] = matrix[i][0] * r + matrix[i][1] * g + matrix[i][2] * b;
	return xyz;
}
//#endregion
//#region node_modules/vuetify/lib/util/colorUtils.js
function isCssColor(color) {
	return !!color && /^(#|var\(--|(rgb|hsl)a?\()/.test(color);
}
function isParsableColor(color) {
	return isCssColor(color) && !/^((rgb|hsl)a?\()?var\(--/.test(color);
}
var cssColorRe = /^(?<fn>(?:rgb|hsl)a?)\((?<values>.+)\)/;
var mappers = {
	rgb: (r, g, b, a) => ({
		r,
		g,
		b,
		a
	}),
	rgba: (r, g, b, a) => ({
		r,
		g,
		b,
		a
	}),
	hsl: (h, s, l, a) => HSLtoRGB({
		h,
		s,
		l,
		a
	}),
	hsla: (h, s, l, a) => HSLtoRGB({
		h,
		s,
		l,
		a
	}),
	hsv: (h, s, v, a) => HSVtoRGB({
		h,
		s,
		v,
		a
	}),
	hsva: (h, s, v, a) => HSVtoRGB({
		h,
		s,
		v,
		a
	})
};
function parseColor(color) {
	if (typeof color === "number") {
		if (isNaN(color) || color < 0 || color > 16777215) consoleWarn(`'${color}' is not a valid hex color`);
		return {
			r: (color & 16711680) >> 16,
			g: (color & 65280) >> 8,
			b: color & 255
		};
	} else if (typeof color === "string" && cssColorRe.test(color)) {
		const { groups } = color.match(cssColorRe);
		const { fn, values } = groups;
		const realValues = values.split(/,\s*|\s*\/\s*|\s+/).map((v, i) => {
			if (v.endsWith("%") || i > 0 && i < 3 && [
				"hsl",
				"hsla",
				"hsv",
				"hsva"
			].includes(fn)) return parseFloat(v) / 100;
			else return parseFloat(v);
		});
		return mappers[fn](...realValues);
	} else if (typeof color === "string") {
		let hex = color.startsWith("#") ? color.slice(1) : color;
		if ([3, 4].includes(hex.length)) hex = hex.split("").map((char) => char + char).join("");
		else if (![6, 8].includes(hex.length)) consoleWarn(`'${color}' is not a valid hex(a) color`);
		const int = parseInt(hex, 16);
		if (isNaN(int) || int < 0 || int > 4294967295) consoleWarn(`'${color}' is not a valid hex(a) color`);
		return HexToRGB(hex);
	} else if (typeof color === "object") {
		if (has(color, [
			"r",
			"g",
			"b"
		])) return color;
		else if (has(color, [
			"h",
			"s",
			"l"
		])) return HSVtoRGB(HSLtoHSV(color));
		else if (has(color, [
			"h",
			"s",
			"v"
		])) return HSVtoRGB(color);
	}
	throw new TypeError(`Invalid color: ${color == null ? color : String(color) || color.constructor.name}\nExpected #hex, #hexa, rgb(), rgba(), hsl(), hsla(), object or number`);
}
/** Converts HSVA to RGBA. Based on formula from https://en.wikipedia.org/wiki/HSL_and_HSV */
function HSVtoRGB(hsva) {
	const { h, s, v, a } = hsva;
	const f = (n) => {
		const k = (n + h / 60) % 6;
		return v - v * s * Math.max(Math.min(k, 4 - k, 1), 0);
	};
	const rgb = [
		f(5),
		f(3),
		f(1)
	].map((v) => Math.round(v * 255));
	return {
		r: rgb[0],
		g: rgb[1],
		b: rgb[2],
		a
	};
}
function HSLtoRGB(hsla) {
	return HSVtoRGB(HSLtoHSV(hsla));
}
/** Converts RGBA to HSVA. Based on formula from https://en.wikipedia.org/wiki/HSL_and_HSV */
function RGBtoHSV(rgba) {
	if (!rgba) return {
		h: 0,
		s: 1,
		v: 1,
		a: 1
	};
	const r = rgba.r / 255;
	const g = rgba.g / 255;
	const b = rgba.b / 255;
	const max = Math.max(r, g, b);
	const min = Math.min(r, g, b);
	let h = 0;
	if (max !== min) {
		if (max === r) h = 60 * (0 + (g - b) / (max - min));
		else if (max === g) h = 60 * (2 + (b - r) / (max - min));
		else if (max === b) h = 60 * (4 + (r - g) / (max - min));
	}
	if (h < 0) h = h + 360;
	const s = max === 0 ? 0 : (max - min) / max;
	const hsv = [
		h,
		s,
		max
	];
	return {
		h: hsv[0],
		s: hsv[1],
		v: hsv[2],
		a: rgba.a
	};
}
function HSVtoHSL(hsva) {
	const { h, s, v, a } = hsva;
	const l = v - v * s / 2;
	return {
		h,
		s: l === 1 || l === 0 ? 0 : (v - l) / Math.min(l, 1 - l),
		l,
		a
	};
}
function HSLtoHSV(hsl) {
	const { h, s, l, a } = hsl;
	const v = l + s * Math.min(l, 1 - l);
	return {
		h,
		s: v === 0 ? 0 : 2 - 2 * l / v,
		v,
		a
	};
}
function RGBtoCSS(_ref) {
	let { r, g, b, a } = _ref;
	return a === void 0 ? `rgb(${r}, ${g}, ${b})` : `rgba(${r}, ${g}, ${b}, ${a})`;
}
function HSVtoCSS(hsva) {
	return RGBtoCSS(HSVtoRGB(hsva));
}
function toHex(v) {
	const h = Math.round(v).toString(16);
	return ("00".substr(0, 2 - h.length) + h).toUpperCase();
}
function RGBtoHex(_ref2) {
	let { r, g, b, a } = _ref2;
	return `#${[
		toHex(r),
		toHex(g),
		toHex(b),
		a !== void 0 ? toHex(Math.round(a * 255)) : ""
	].join("")}`;
}
function HexToRGB(hex) {
	hex = parseHex(hex);
	let [r, g, b, a] = chunk(hex, 2).map((c) => parseInt(c, 16));
	a = a === void 0 ? a : a / 255;
	return {
		r,
		g,
		b,
		a
	};
}
function HexToHSV(hex) {
	return RGBtoHSV(HexToRGB(hex));
}
function HSVtoHex(hsva) {
	return RGBtoHex(HSVtoRGB(hsva));
}
function parseHex(hex) {
	if (hex.startsWith("#")) hex = hex.slice(1);
	hex = hex.replace(/([^0-9a-f])/gi, "F");
	if (hex.length === 3 || hex.length === 4) hex = hex.split("").map((x) => x + x).join("");
	if (hex.length !== 6) hex = padEnd(padEnd(hex, 6), 8, "F");
	return hex;
}
function lighten(value, amount) {
	const lab = fromXYZ$1(toXYZ(value));
	lab[0] = lab[0] + amount * 10;
	return fromXYZ(toXYZ$1(lab));
}
function darken(value, amount) {
	const lab = fromXYZ$1(toXYZ(value));
	lab[0] = lab[0] - amount * 10;
	return fromXYZ(toXYZ$1(lab));
}
/**
* Calculate the relative luminance of a given color
* @see https://www.w3.org/TR/WCAG20/#relativeluminancedef
*/
function getLuma(color) {
	return toXYZ(parseColor(color))[1];
}
/**
* Returns the contrast ratio (1-21) between two colors.
* @see https://www.w3.org/TR/WCAG20/#contrast-ratiodef
*/
function getContrast(first, second) {
	const l1 = getLuma(first);
	const l2 = getLuma(second);
	const light = Math.max(l1, l2);
	const dark = Math.min(l1, l2);
	return (light + .05) / (dark + .05);
}
function getForeground(color) {
	const blackContrast = Math.abs(APCAcontrast(parseColor(0), parseColor(color)));
	return Math.abs(APCAcontrast(parseColor(16777215), parseColor(color))) > Math.min(blackContrast, 50) ? "#fff" : "#000";
}
//#endregion
//#region node_modules/vuetify/lib/util/propsFactory.js
/**
* Creates a factory function for props definitions.
* This is used to define props in a composable then override
* default values in an implementing component.
*
* @example Simplified signature
* (props: Props) => (defaults?: Record<keyof props, any>) => Props
*
* @example Usage
* const makeProps = propsFactory({
*   foo: String,
* })
*
* defineComponent({
*   props: {
*     ...makeProps({
*       foo: 'a',
*     }),
*   },
*   setup (props) {
*     // would be "string | undefined", now "string" because a default has been provided
*     props.foo
*   },
* }
*/
function propsFactory(props, source) {
	return (defaults) => {
		return Object.keys(props).reduce((obj, prop) => {
			const definition = typeof props[prop] === "object" && props[prop] != null && !Array.isArray(props[prop]) ? props[prop] : { type: props[prop] };
			if (defaults && prop in defaults) obj[prop] = {
				...definition,
				default: defaults[prop]
			};
			else obj[prop] = definition;
			if (source && !obj[prop].source) obj[prop].source = source;
			return obj;
		}, {});
	};
}
/**
* Like `Partial<T>` but doesn't care what the value is
*/
//#endregion
//#region node_modules/vuetify/lib/composables/component.js
var makeComponentProps = propsFactory({
	class: [
		String,
		Array,
		Object
	],
	style: {
		type: [
			String,
			Array,
			Object
		],
		default: null
	}
}, "component");
//#endregion
//#region node_modules/vuetify/lib/util/getCurrentInstance.js
function getCurrentInstance$1(name, message) {
	const vm = getCurrentInstance();
	if (!vm) throw new Error(`[Vuetify] ${name} ${message || "must be called from inside a setup function"}`);
	return vm;
}
function getCurrentInstanceName() {
	const vm = getCurrentInstance$1(arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : "composables").type;
	return toKebabCase(vm?.aliasName || vm?.name);
}
//#endregion
//#region node_modules/vuetify/lib/util/injectSelf.js
function injectSelf(key) {
	const { provides } = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstance$1("injectSelf");
	if (provides && key in provides) return provides[key];
}
//#endregion
//#region node_modules/vuetify/lib/composables/defaults.js
var DefaultsSymbol = Symbol.for("vuetify:defaults");
function createDefaults(options) {
	return ref(options);
}
function injectDefaults() {
	const defaults = inject(DefaultsSymbol);
	if (!defaults) throw new Error("[Vuetify] Could not find defaults instance");
	return defaults;
}
function provideDefaults(defaults, options) {
	const injectedDefaults = injectDefaults();
	const providedDefaults = ref(defaults);
	const newDefaults = computed(() => {
		if (unref(options?.disabled)) return injectedDefaults.value;
		const scoped = unref(options?.scoped);
		const reset = unref(options?.reset);
		const root = unref(options?.root);
		if (providedDefaults.value == null && !(scoped || reset || root)) return injectedDefaults.value;
		let properties = mergeDeep(providedDefaults.value, { prev: injectedDefaults.value });
		if (scoped) return properties;
		if (reset || root) {
			const len = Number(reset || Infinity);
			for (let i = 0; i <= len; i++) {
				if (!properties || !("prev" in properties)) break;
				properties = properties.prev;
			}
			if (properties && typeof root === "string" && root in properties) properties = mergeDeep(mergeDeep(properties, { prev: properties }), properties[root]);
			return properties;
		}
		return properties.prev ? mergeDeep(properties.prev, properties) : properties;
	});
	provide(DefaultsSymbol, newDefaults);
	return newDefaults;
}
function propIsDefined(vnode, prop) {
	return vnode.props && (typeof vnode.props[prop] !== "undefined" || typeof vnode.props[toKebabCase(prop)] !== "undefined");
}
function internalUseDefaults() {
	let props = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : {};
	let name = arguments.length > 1 ? arguments[1] : void 0;
	let defaults = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : injectDefaults();
	const vm = getCurrentInstance$1("useDefaults");
	name = name ?? vm.type.name ?? vm.type.__name;
	if (!name) throw new Error("[Vuetify] Could not determine component name");
	const componentDefaults = computed(() => defaults.value?.[props._as ?? name]);
	const _props = new Proxy(props, { get(target, prop) {
		const propValue = Reflect.get(target, prop);
		if (prop === "class" || prop === "style") return [componentDefaults.value?.[prop], propValue].filter((v) => v != null);
		if (propIsDefined(vm.vnode, prop)) return propValue;
		const _componentDefault = componentDefaults.value?.[prop];
		if (_componentDefault !== void 0) return _componentDefault;
		const _globalDefault = defaults.value?.global?.[prop];
		if (_globalDefault !== void 0) return _globalDefault;
		return propValue;
	} });
	const _subcomponentDefaults = shallowRef();
	watchEffect(() => {
		if (componentDefaults.value) {
			const subComponents = Object.entries(componentDefaults.value).filter((_ref) => {
				let [key] = _ref;
				return key.startsWith(key[0].toUpperCase());
			});
			_subcomponentDefaults.value = subComponents.length ? Object.fromEntries(subComponents) : void 0;
		} else _subcomponentDefaults.value = void 0;
	});
	function provideSubDefaults() {
		const injected = injectSelf(DefaultsSymbol, vm);
		provide(DefaultsSymbol, computed(() => {
			return _subcomponentDefaults.value ? mergeDeep(injected?.value ?? {}, _subcomponentDefaults.value) : injected?.value;
		}));
	}
	return {
		props: _props,
		provideSubDefaults
	};
}
//#endregion
//#region node_modules/vuetify/lib/util/defineComponent.js
function defineComponent$1(options) {
	options._setup = options._setup ?? options.setup;
	if (!options.name) {
		consoleWarn("The component is missing an explicit name, unable to generate default prop value");
		return options;
	}
	if (options._setup) {
		options.props = propsFactory(options.props ?? {}, options.name)();
		const propKeys = Object.keys(options.props).filter((key) => key !== "class" && key !== "style");
		options.filterProps = function filterProps(props) {
			return pick(props, propKeys);
		};
		options.props._as = String;
		options.setup = function setup(props, ctx) {
			const defaults = injectDefaults();
			if (!defaults.value) return options._setup(props, ctx);
			const { props: _props, provideSubDefaults } = internalUseDefaults(props, props._as ?? options.name, defaults);
			const setupBindings = options._setup(_props, ctx);
			provideSubDefaults();
			return setupBindings;
		};
	}
	return options;
}
function genericComponent() {
	let exposeDefaults = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : true;
	return (options) => (exposeDefaults ? defineComponent$1 : defineComponent)(options);
}
function defineFunctionalComponent(props, render) {
	render.props = props;
	return render;
}
//#endregion
//#region node_modules/vuetify/lib/util/createSimpleFunctional.js
function createSimpleFunctional(klass) {
	let tag = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : "div";
	let name = arguments.length > 2 ? arguments[2] : void 0;
	return genericComponent()({
		name: name ?? capitalize(camelize(klass.replace(/__/g, "-"))),
		props: {
			tag: {
				type: String,
				default: tag
			},
			...makeComponentProps()
		},
		setup(props, _ref) {
			let { slots } = _ref;
			return () => {
				return h(props.tag, {
					class: [klass, props.class],
					style: props.style
				}, slots.default?.());
			};
		}
	});
}
//#endregion
//#region node_modules/vuetify/lib/util/dom.js
/**
* Returns:
*  - 'null' if the node is not attached to the DOM
*  - the root node (HTMLDocument | ShadowRoot) otherwise
*/
function attachedRoot(node) {
	/* istanbul ignore next */
	if (typeof node.getRootNode !== "function") {
		while (node.parentNode) node = node.parentNode;
		if (node !== document) return null;
		return document;
	}
	const root = node.getRootNode();
	if (root !== document && root.getRootNode({ composed: true }) !== document) return null;
	return root;
}
//#endregion
//#region node_modules/vuetify/lib/util/easing.js
var standardEasing = "cubic-bezier(0.4, 0, 0.2, 1)";
var deceleratedEasing = "cubic-bezier(0.0, 0, 0.2, 1)";
var acceleratedEasing = "cubic-bezier(0.4, 0, 1, 1)";
//#endregion
//#region node_modules/vuetify/lib/util/events.js
function getPrefixedEventHandlers(attrs, suffix, getData) {
	return Object.keys(attrs).filter((key) => isOn(key) && key.endsWith(suffix)).reduce((acc, key) => {
		acc[key.slice(0, -suffix.length)] = (event) => attrs[key](event, getData(event));
		return acc;
	}, {});
}
//#endregion
//#region node_modules/vuetify/lib/util/getScrollParent.js
function getScrollParent(el) {
	let includeHidden = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : false;
	while (el) {
		if (includeHidden ? isPotentiallyScrollable(el) : hasScrollbar(el)) return el;
		el = el.parentElement;
	}
	return document.scrollingElement;
}
function getScrollParents(el, stopAt) {
	const elements = [];
	if (stopAt && el && !stopAt.contains(el)) return elements;
	while (el) {
		if (hasScrollbar(el)) elements.push(el);
		if (el === stopAt) break;
		el = el.parentElement;
	}
	return elements;
}
function hasScrollbar(el) {
	if (!el || el.nodeType !== Node.ELEMENT_NODE) return false;
	const style = window.getComputedStyle(el);
	return style.overflowY === "scroll" || style.overflowY === "auto" && el.scrollHeight > el.clientHeight;
}
function isPotentiallyScrollable(el) {
	if (!el || el.nodeType !== Node.ELEMENT_NODE) return false;
	const style = window.getComputedStyle(el);
	return ["scroll", "auto"].includes(style.overflowY);
}
//#endregion
//#region node_modules/vuetify/lib/util/isFixedPosition.js
function isFixedPosition(el) {
	while (el) {
		if (window.getComputedStyle(el).position === "fixed") return true;
		el = el.offsetParent;
	}
	return false;
}
//#endregion
//#region node_modules/vuetify/lib/util/useRender.js
function useRender(render) {
	const vm = getCurrentInstance$1("useRender");
	vm.render = render;
}
//#endregion
//#region node_modules/vuetify/lib/composables/proxiedModel.js
function useProxiedModel(props, prop, defaultValue) {
	let transformIn = arguments.length > 3 && arguments[3] !== void 0 ? arguments[3] : (v) => v;
	let transformOut = arguments.length > 4 && arguments[4] !== void 0 ? arguments[4] : (v) => v;
	const vm = getCurrentInstance$1("useProxiedModel");
	const internal = ref(props[prop] !== void 0 ? props[prop] : defaultValue);
	const kebabProp = toKebabCase(prop);
	const isControlled = kebabProp !== prop ? computed(() => {
		props[prop];
		return !!((vm.vnode.props?.hasOwnProperty(prop) || vm.vnode.props?.hasOwnProperty(kebabProp)) && (vm.vnode.props?.hasOwnProperty(`onUpdate:${prop}`) || vm.vnode.props?.hasOwnProperty(`onUpdate:${kebabProp}`)));
	}) : computed(() => {
		props[prop];
		return !!(vm.vnode.props?.hasOwnProperty(prop) && vm.vnode.props?.hasOwnProperty(`onUpdate:${prop}`));
	});
	useToggleScope(() => !isControlled.value, () => {
		watch(() => props[prop], (val) => {
			internal.value = val;
		});
	});
	const model = computed({
		get() {
			const externalValue = props[prop];
			return transformIn(isControlled.value ? externalValue : internal.value);
		},
		set(internalValue) {
			const newValue = transformOut(internalValue);
			const value = toRaw(isControlled.value ? props[prop] : internal.value);
			if (value === newValue || transformIn(value) === internalValue) return;
			internal.value = newValue;
			vm?.emit(`update:${prop}`, newValue);
		}
	});
	Object.defineProperty(model, "externalValue", { get: () => isControlled.value ? props[prop] : internal.value });
	return model;
}
//#endregion
//#region node_modules/vuetify/lib/locale/en.js
var en_default = {
	badge: "Badge",
	open: "Open",
	close: "Close",
	dismiss: "Dismiss",
	confirmEdit: {
		ok: "OK",
		cancel: "Cancel"
	},
	dataIterator: {
		noResultsText: "No matching records found",
		loadingText: "Loading items..."
	},
	dataTable: {
		itemsPerPageText: "Rows per page:",
		ariaLabel: {
			sortDescending: "Sorted descending.",
			sortAscending: "Sorted ascending.",
			sortNone: "Not sorted.",
			activateNone: "Activate to remove sorting.",
			activateDescending: "Activate to sort descending.",
			activateAscending: "Activate to sort ascending."
		},
		sortBy: "Sort by"
	},
	dataFooter: {
		itemsPerPageText: "Items per page:",
		itemsPerPageAll: "All",
		nextPage: "Next page",
		prevPage: "Previous page",
		firstPage: "First page",
		lastPage: "Last page",
		pageText: "{0}-{1} of {2}"
	},
	dateRangeInput: { divider: "to" },
	datePicker: {
		itemsSelected: "{0} selected",
		range: {
			title: "Select dates",
			header: "Enter dates"
		},
		title: "Select date",
		header: "Enter date",
		input: { placeholder: "Enter date" }
	},
	noDataText: "No data available",
	carousel: {
		prev: "Previous visual",
		next: "Next visual",
		ariaLabel: { delimiter: "Carousel slide {0} of {1}" }
	},
	calendar: {
		moreEvents: "{0} more",
		today: "Today"
	},
	input: {
		clear: "Clear {0}",
		prependAction: "{0} prepended action",
		appendAction: "{0} appended action",
		otp: "Please enter OTP character {0}"
	},
	fileInput: {
		counter: "{0} files",
		counterSize: "{0} files ({1} in total)"
	},
	fileUpload: {
		title: "Drag and drop files here",
		divider: "or",
		browse: "Browse Files"
	},
	timePicker: {
		am: "AM",
		pm: "PM",
		title: "Select Time"
	},
	pagination: { ariaLabel: {
		root: "Pagination Navigation",
		next: "Next page",
		previous: "Previous page",
		page: "Go to page {0}",
		currentPage: "Page {0}, Current page",
		first: "First page",
		last: "Last page"
	} },
	stepper: {
		next: "Next",
		prev: "Previous"
	},
	rating: { ariaLabel: { item: "Rating {0} of {1}" } },
	loading: "Loading...",
	infiniteScroll: {
		loadMore: "Load more",
		empty: "No more"
	},
	rules: {
		required: "This field is required",
		email: "Please enter a valid email",
		number: "This field can only contain numbers",
		integer: "This field can only contain integer values",
		capital: "This field can only contain uppercase letters",
		maxLength: "You must enter a maximum of {0} characters",
		minLength: "You must enter a minimum of {0} characters",
		strictLength: "The length of the entered field is invalid",
		exclude: "The {0} character is not allowed",
		notEmpty: "Please choose at least one value",
		pattern: "Invalid format"
	}
};
//#endregion
//#region node_modules/vuetify/lib/locale/adapters/vuetify.js
var LANG_PREFIX = "$vuetify.";
var replace = (str, params) => {
	return str.replace(/\{(\d+)\}/g, (match, index) => {
		return String(params[Number(index)]);
	});
};
var createTranslateFunction = (current, fallback, messages) => {
	return function(key) {
		for (var _len = arguments.length, params = new Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) params[_key - 1] = arguments[_key];
		if (!key.startsWith(LANG_PREFIX)) return replace(key, params);
		const shortKey = key.replace(LANG_PREFIX, "");
		const currentLocale = current.value && messages.value[current.value];
		const fallbackLocale = fallback.value && messages.value[fallback.value];
		let str = getObjectValueByPath(currentLocale, shortKey, null);
		if (!str) {
			consoleWarn(`Translation key "${key}" not found in "${current.value}", trying fallback locale`);
			str = getObjectValueByPath(fallbackLocale, shortKey, null);
		}
		if (!str) {
			consoleError(`Translation key "${key}" not found in fallback`);
			str = key;
		}
		if (typeof str !== "string") {
			consoleError(`Translation key "${key}" has a non-string value`);
			str = key;
		}
		return replace(str, params);
	};
};
function createNumberFunction(current, fallback) {
	return (value, options) => {
		return new Intl.NumberFormat([current.value, fallback.value], options).format(value);
	};
}
function useProvided(props, prop, provided) {
	const internal = useProxiedModel(props, prop, props[prop] ?? provided.value);
	internal.value = props[prop] ?? provided.value;
	watch(provided, (v) => {
		if (props[prop] == null) internal.value = provided.value;
	});
	return internal;
}
function createProvideFunction(state) {
	return (props) => {
		const current = useProvided(props, "locale", state.current);
		const fallback = useProvided(props, "fallback", state.fallback);
		const messages = useProvided(props, "messages", state.messages);
		return {
			name: "vuetify",
			current,
			fallback,
			messages,
			t: createTranslateFunction(current, fallback, messages),
			n: createNumberFunction(current, fallback),
			provide: createProvideFunction({
				current,
				fallback,
				messages
			})
		};
	};
}
function createVuetifyAdapter(options) {
	const current = shallowRef(options?.locale ?? "en");
	const fallback = shallowRef(options?.fallback ?? "en");
	const messages = ref({
		en: en_default,
		...options?.messages
	});
	return {
		name: "vuetify",
		current,
		fallback,
		messages,
		t: createTranslateFunction(current, fallback, messages),
		n: createNumberFunction(current, fallback),
		provide: createProvideFunction({
			current,
			fallback,
			messages
		})
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/locale.js
var LocaleSymbol = Symbol.for("vuetify:locale");
function isLocaleInstance(obj) {
	return obj.name != null;
}
function createLocale(options) {
	const i18n = options?.adapter && isLocaleInstance(options?.adapter) ? options?.adapter : createVuetifyAdapter(options);
	const rtl = createRtl(i18n, options);
	return {
		...i18n,
		...rtl
	};
}
function useLocale() {
	const locale = inject(LocaleSymbol);
	if (!locale) throw new Error("[Vuetify] Could not find injected locale instance");
	return locale;
}
function provideLocale(props) {
	const locale = inject(LocaleSymbol);
	if (!locale) throw new Error("[Vuetify] Could not find injected locale instance");
	const i18n = locale.provide(props);
	const rtl = provideRtl(i18n, locale.rtl, props);
	const data = {
		...i18n,
		...rtl
	};
	provide(LocaleSymbol, data);
	return data;
}
function genDefaults$3() {
	return {
		af: false,
		ar: true,
		bg: false,
		ca: false,
		ckb: false,
		cs: false,
		de: false,
		el: false,
		en: false,
		es: false,
		et: false,
		fa: true,
		fi: false,
		fr: false,
		hr: false,
		hu: false,
		he: true,
		id: false,
		it: false,
		ja: false,
		km: false,
		ko: false,
		lv: false,
		lt: false,
		nl: false,
		no: false,
		pl: false,
		pt: false,
		ro: false,
		ru: false,
		sk: false,
		sl: false,
		srCyrl: false,
		srLatn: false,
		sv: false,
		th: false,
		tr: false,
		az: false,
		uk: false,
		vi: false,
		zhHans: false,
		zhHant: false
	};
}
function createRtl(i18n, options) {
	const rtl = ref(options?.rtl ?? genDefaults$3());
	const isRtl = computed(() => rtl.value[i18n.current.value] ?? false);
	return {
		isRtl,
		rtl,
		rtlClasses: toRef(() => `v-locale--is-${isRtl.value ? "rtl" : "ltr"}`)
	};
}
function provideRtl(locale, rtl, props) {
	const isRtl = computed(() => props.rtl ?? rtl.value[locale.current.value] ?? false);
	return {
		isRtl,
		rtl,
		rtlClasses: toRef(() => `v-locale--is-${isRtl.value ? "rtl" : "ltr"}`)
	};
}
function useRtl() {
	const locale = inject(LocaleSymbol);
	if (!locale) throw new Error("[Vuetify] Could not find injected rtl instance");
	return {
		isRtl: locale.isRtl,
		rtlClasses: locale.rtlClasses
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/date/adapters/vuetify.js
function weekInfo(locale) {
	const code = locale.slice(-2).toUpperCase();
	switch (true) {
		case locale === "GB-alt-variant": return {
			firstDay: 0,
			firstWeekSize: 4
		};
		case locale === "001": return {
			firstDay: 1,
			firstWeekSize: 1
		};
		case `AG AS BD BR BS BT BW BZ CA CO DM DO ET GT GU HK HN ID IL IN JM JP KE
    KH KR LA MH MM MO MT MX MZ NI NP PA PE PH PK PR PY SA SG SV TH TT TW UM US
    VE VI WS YE ZA ZW`.includes(code): return {
			firstDay: 0,
			firstWeekSize: 1
		};
		case `AI AL AM AR AU AZ BA BM BN BY CL CM CN CR CY EC GE HR KG KZ LB LK LV
    MD ME MK MN MY NZ RO RS SI TJ TM TR UA UY UZ VN XK`.includes(code): return {
			firstDay: 1,
			firstWeekSize: 1
		};
		case `AD AN AT AX BE BG CH CZ DE DK EE ES FI FJ FO FR GB GF GP GR HU IE IS
    IT LI LT LU MC MQ NL NO PL RE RU SE SK SM VA`.includes(code): return {
			firstDay: 1,
			firstWeekSize: 4
		};
		case `AE AF BH DJ DZ EG IQ IR JO KW LY OM QA SD SY`.includes(code): return {
			firstDay: 6,
			firstWeekSize: 1
		};
		case code === "MV": return {
			firstDay: 5,
			firstWeekSize: 1
		};
		case code === "PT": return {
			firstDay: 0,
			firstWeekSize: 4
		};
		default: return null;
	}
}
function getWeekArray(date, locale, firstDayOfWeek) {
	const weeks = [];
	let currentWeek = [];
	const firstDayOfMonth = startOfMonth(date);
	const lastDayOfMonth = endOfMonth(date);
	const first = firstDayOfWeek ?? weekInfo(locale)?.firstDay ?? 0;
	const firstDayWeekIndex = (firstDayOfMonth.getDay() - first + 7) % 7;
	const lastDayWeekIndex = (lastDayOfMonth.getDay() - first + 7) % 7;
	for (let i = 0; i < firstDayWeekIndex; i++) {
		const adjacentDay = new Date(firstDayOfMonth);
		adjacentDay.setDate(adjacentDay.getDate() - (firstDayWeekIndex - i));
		currentWeek.push(adjacentDay);
	}
	for (let i = 1; i <= lastDayOfMonth.getDate(); i++) {
		const day = new Date(date.getFullYear(), date.getMonth(), i);
		currentWeek.push(day);
		if (currentWeek.length === 7) {
			weeks.push(currentWeek);
			currentWeek = [];
		}
	}
	for (let i = 1; i < 7 - lastDayWeekIndex; i++) {
		const adjacentDay = new Date(lastDayOfMonth);
		adjacentDay.setDate(adjacentDay.getDate() + i);
		currentWeek.push(adjacentDay);
	}
	if (currentWeek.length > 0) weeks.push(currentWeek);
	return weeks;
}
function startOfWeek(date, locale, firstDayOfWeek) {
	const day = firstDayOfWeek ?? weekInfo(locale)?.firstDay ?? 0;
	const d = new Date(date);
	while (d.getDay() !== day) d.setDate(d.getDate() - 1);
	return d;
}
function endOfWeek(date, locale) {
	const d = new Date(date);
	const lastDay = ((weekInfo(locale)?.firstDay ?? 0) + 6) % 7;
	while (d.getDay() !== lastDay) d.setDate(d.getDate() + 1);
	return d;
}
function startOfMonth(date) {
	return new Date(date.getFullYear(), date.getMonth(), 1);
}
function endOfMonth(date) {
	return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}
function parseLocalDate(value) {
	const parts = value.split("-").map(Number);
	return new Date(parts[0], parts[1] - 1, parts[2]);
}
var _YYYMMDD = /^([12]\d{3}-([1-9]|0[1-9]|1[0-2])-([1-9]|0[1-9]|[12]\d|3[01]))$/;
function date(value) {
	if (value == null) return /* @__PURE__ */ new Date();
	if (value instanceof Date) return value;
	if (typeof value === "string") {
		let parsed;
		if (_YYYMMDD.test(value)) return parseLocalDate(value);
		else parsed = Date.parse(value);
		if (!isNaN(parsed)) return new Date(parsed);
	}
	return null;
}
var sundayJanuarySecond2000 = new Date(2e3, 0, 2);
function getWeekdays(locale, firstDayOfWeek) {
	const daysFromSunday = firstDayOfWeek ?? weekInfo(locale)?.firstDay ?? 0;
	return createRange(7).map((i) => {
		const weekday = new Date(sundayJanuarySecond2000);
		weekday.setDate(sundayJanuarySecond2000.getDate() + daysFromSunday + i);
		return new Intl.DateTimeFormat(locale, { weekday: "narrow" }).format(weekday);
	});
}
function format(value, formatString, locale, formats) {
	const newDate = date(value) ?? /* @__PURE__ */ new Date();
	const customFormat = formats?.[formatString];
	if (typeof customFormat === "function") return customFormat(newDate, formatString, locale);
	let options = {};
	switch (formatString) {
		case "fullDate":
			options = {
				year: "numeric",
				month: "long",
				day: "numeric"
			};
			break;
		case "fullDateWithWeekday":
			options = {
				weekday: "long",
				year: "numeric",
				month: "long",
				day: "numeric"
			};
			break;
		case "normalDate": return `${newDate.getDate()} ${new Intl.DateTimeFormat(locale, { month: "long" }).format(newDate)}`;
		case "normalDateWithWeekday":
			options = {
				weekday: "short",
				day: "numeric",
				month: "short"
			};
			break;
		case "shortDate":
			options = {
				month: "short",
				day: "numeric"
			};
			break;
		case "year":
			options = { year: "numeric" };
			break;
		case "month":
			options = { month: "long" };
			break;
		case "monthShort":
			options = { month: "short" };
			break;
		case "monthAndYear":
			options = {
				month: "long",
				year: "numeric"
			};
			break;
		case "monthAndDate":
			options = {
				month: "long",
				day: "numeric"
			};
			break;
		case "weekday":
			options = { weekday: "long" };
			break;
		case "weekdayShort":
			options = { weekday: "short" };
			break;
		case "dayOfMonth": return new Intl.NumberFormat(locale).format(newDate.getDate());
		case "hours12h":
			options = {
				hour: "numeric",
				hour12: true
			};
			break;
		case "hours24h":
			options = {
				hour: "numeric",
				hour12: false
			};
			break;
		case "minutes":
			options = { minute: "numeric" };
			break;
		case "seconds":
			options = { second: "numeric" };
			break;
		case "fullTime":
			options = {
				hour: "numeric",
				minute: "numeric"
			};
			break;
		case "fullTime12h":
			options = {
				hour: "numeric",
				minute: "numeric",
				hour12: true
			};
			break;
		case "fullTime24h":
			options = {
				hour: "numeric",
				minute: "numeric",
				hour12: false
			};
			break;
		case "fullDateTime":
			options = {
				year: "numeric",
				month: "short",
				day: "numeric",
				hour: "numeric",
				minute: "numeric"
			};
			break;
		case "fullDateTime12h":
			options = {
				year: "numeric",
				month: "short",
				day: "numeric",
				hour: "numeric",
				minute: "numeric",
				hour12: true
			};
			break;
		case "fullDateTime24h":
			options = {
				year: "numeric",
				month: "short",
				day: "numeric",
				hour: "numeric",
				minute: "numeric",
				hour12: false
			};
			break;
		case "keyboardDate":
			options = {
				year: "numeric",
				month: "2-digit",
				day: "2-digit"
			};
			break;
		case "keyboardDateTime":
			options = {
				year: "numeric",
				month: "2-digit",
				day: "2-digit",
				hour: "numeric",
				minute: "numeric"
			};
			return new Intl.DateTimeFormat(locale, options).format(newDate).replace(/, /g, " ");
		case "keyboardDateTime12h":
			options = {
				year: "numeric",
				month: "2-digit",
				day: "2-digit",
				hour: "numeric",
				minute: "numeric",
				hour12: true
			};
			return new Intl.DateTimeFormat(locale, options).format(newDate).replace(/, /g, " ");
		case "keyboardDateTime24h":
			options = {
				year: "numeric",
				month: "2-digit",
				day: "2-digit",
				hour: "numeric",
				minute: "numeric",
				hour12: false
			};
			return new Intl.DateTimeFormat(locale, options).format(newDate).replace(/, /g, " ");
		default: options = customFormat ?? {
			timeZone: "UTC",
			timeZoneName: "short"
		};
	}
	return new Intl.DateTimeFormat(locale, options).format(newDate);
}
function toISO(adapter, value) {
	const date = adapter.toJsDate(value);
	return `${date.getFullYear()}-${padStart(String(date.getMonth() + 1), 2, "0")}-${padStart(String(date.getDate()), 2, "0")}`;
}
function parseISO(value) {
	const [year, month, day] = value.split("-").map(Number);
	return new Date(year, month - 1, day);
}
function addMinutes(date, amount) {
	const d = new Date(date);
	d.setMinutes(d.getMinutes() + amount);
	return d;
}
function addHours(date, amount) {
	const d = new Date(date);
	d.setHours(d.getHours() + amount);
	return d;
}
function addDays(date, amount) {
	const d = new Date(date);
	d.setDate(d.getDate() + amount);
	return d;
}
function addWeeks(date, amount) {
	const d = new Date(date);
	d.setDate(d.getDate() + amount * 7);
	return d;
}
function addMonths(date, amount) {
	const d = new Date(date);
	d.setDate(1);
	d.setMonth(d.getMonth() + amount);
	return d;
}
function getYear(date) {
	return date.getFullYear();
}
function getMonth(date) {
	return date.getMonth();
}
function getWeek(date, locale, firstDayOfWeek, firstWeekMinSize) {
	const weekInfoFromLocale = weekInfo(locale);
	const weekStart = firstDayOfWeek ?? weekInfoFromLocale?.firstDay ?? 0;
	const minWeekSize = firstWeekMinSize ?? weekInfoFromLocale?.firstWeekSize ?? 1;
	function firstWeekSize(year) {
		const yearStart = new Date(year, 0, 1);
		return 7 - getDiff(yearStart, startOfWeek(yearStart, locale, weekStart), "days");
	}
	let year = getYear(date);
	const currentWeekEnd = addDays(startOfWeek(date, locale, weekStart), 6);
	if (year < getYear(currentWeekEnd) && firstWeekSize(year + 1) >= minWeekSize) year++;
	const yearStart = new Date(year, 0, 1);
	const size = firstWeekSize(year);
	return 1 + getDiff(date, size >= minWeekSize ? addDays(yearStart, size - 7) : addDays(yearStart, size), "weeks");
}
function getDate(date) {
	return date.getDate();
}
function getNextMonth(date) {
	return new Date(date.getFullYear(), date.getMonth() + 1, 1);
}
function getPreviousMonth(date) {
	return new Date(date.getFullYear(), date.getMonth() - 1, 1);
}
function getHours(date) {
	return date.getHours();
}
function getMinutes(date) {
	return date.getMinutes();
}
function startOfYear(date) {
	return new Date(date.getFullYear(), 0, 1);
}
function endOfYear(date) {
	return new Date(date.getFullYear(), 11, 31);
}
function isWithinRange(date, range) {
	return isAfter(date, range[0]) && isBefore(date, range[1]);
}
function isValid(date) {
	const d = new Date(date);
	return d instanceof Date && !isNaN(d.getTime());
}
function isAfter(date, comparing) {
	return date.getTime() > comparing.getTime();
}
function isAfterDay(date, comparing) {
	return isAfter(startOfDay(date), startOfDay(comparing));
}
function isBefore(date, comparing) {
	return date.getTime() < comparing.getTime();
}
function isEqual(date, comparing) {
	return date.getTime() === comparing.getTime();
}
function isSameDay(date, comparing) {
	return date.getDate() === comparing.getDate() && date.getMonth() === comparing.getMonth() && date.getFullYear() === comparing.getFullYear();
}
function isSameMonth(date, comparing) {
	return date.getMonth() === comparing.getMonth() && date.getFullYear() === comparing.getFullYear();
}
function isSameYear(date, comparing) {
	return date.getFullYear() === comparing.getFullYear();
}
function getDiff(date, comparing, unit) {
	const d = new Date(date);
	const c = new Date(comparing);
	switch (unit) {
		case "years": return d.getFullYear() - c.getFullYear();
		case "quarters": return Math.floor((d.getMonth() - c.getMonth() + (d.getFullYear() - c.getFullYear()) * 12) / 4);
		case "months": return d.getMonth() - c.getMonth() + (d.getFullYear() - c.getFullYear()) * 12;
		case "weeks": return Math.floor((d.getTime() - c.getTime()) / (1e3 * 60 * 60 * 24 * 7));
		case "days": return Math.floor((d.getTime() - c.getTime()) / (1e3 * 60 * 60 * 24));
		case "hours": return Math.floor((d.getTime() - c.getTime()) / (1e3 * 60 * 60));
		case "minutes": return Math.floor((d.getTime() - c.getTime()) / (1e3 * 60));
		case "seconds": return Math.floor((d.getTime() - c.getTime()) / 1e3);
		default: return d.getTime() - c.getTime();
	}
}
function setHours(date, count) {
	const d = new Date(date);
	d.setHours(count);
	return d;
}
function setMinutes(date, count) {
	const d = new Date(date);
	d.setMinutes(count);
	return d;
}
function setMonth(date, count) {
	const d = new Date(date);
	d.setMonth(count);
	return d;
}
function setDate(date, day) {
	const d = new Date(date);
	d.setDate(day);
	return d;
}
function setYear(date, year) {
	const d = new Date(date);
	d.setFullYear(year);
	return d;
}
function startOfDay(date) {
	return new Date(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0, 0, 0);
}
function endOfDay(date) {
	return new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59, 999);
}
var VuetifyDateAdapter = class {
	constructor(options) {
		this.locale = options.locale;
		this.formats = options.formats;
	}
	date(value) {
		return date(value);
	}
	toJsDate(date) {
		return date;
	}
	toISO(date) {
		return toISO(this, date);
	}
	parseISO(date) {
		return parseISO(date);
	}
	addMinutes(date, amount) {
		return addMinutes(date, amount);
	}
	addHours(date, amount) {
		return addHours(date, amount);
	}
	addDays(date, amount) {
		return addDays(date, amount);
	}
	addWeeks(date, amount) {
		return addWeeks(date, amount);
	}
	addMonths(date, amount) {
		return addMonths(date, amount);
	}
	getWeekArray(date, firstDayOfWeek) {
		const firstDay = firstDayOfWeek !== void 0 ? Number(firstDayOfWeek) : void 0;
		return getWeekArray(date, this.locale, firstDay);
	}
	startOfWeek(date, firstDayOfWeek) {
		const firstDay = firstDayOfWeek !== void 0 ? Number(firstDayOfWeek) : void 0;
		return startOfWeek(date, this.locale, firstDay);
	}
	endOfWeek(date) {
		return endOfWeek(date, this.locale);
	}
	startOfMonth(date) {
		return startOfMonth(date);
	}
	endOfMonth(date) {
		return endOfMonth(date);
	}
	format(date, formatString) {
		return format(date, formatString, this.locale, this.formats);
	}
	isEqual(date, comparing) {
		return isEqual(date, comparing);
	}
	isValid(date) {
		return isValid(date);
	}
	isWithinRange(date, range) {
		return isWithinRange(date, range);
	}
	isAfter(date, comparing) {
		return isAfter(date, comparing);
	}
	isAfterDay(date, comparing) {
		return isAfterDay(date, comparing);
	}
	isBefore(date, comparing) {
		return !isAfter(date, comparing) && !isEqual(date, comparing);
	}
	isSameDay(date, comparing) {
		return isSameDay(date, comparing);
	}
	isSameMonth(date, comparing) {
		return isSameMonth(date, comparing);
	}
	isSameYear(date, comparing) {
		return isSameYear(date, comparing);
	}
	setMinutes(date, count) {
		return setMinutes(date, count);
	}
	setHours(date, count) {
		return setHours(date, count);
	}
	setMonth(date, count) {
		return setMonth(date, count);
	}
	setDate(date, day) {
		return setDate(date, day);
	}
	setYear(date, year) {
		return setYear(date, year);
	}
	getDiff(date, comparing, unit) {
		return getDiff(date, comparing, unit);
	}
	getWeekdays(firstDayOfWeek) {
		const firstDay = firstDayOfWeek !== void 0 ? Number(firstDayOfWeek) : void 0;
		return getWeekdays(this.locale, firstDay);
	}
	getYear(date) {
		return getYear(date);
	}
	getMonth(date) {
		return getMonth(date);
	}
	getWeek(date, firstDayOfWeek, firstWeekMinSize) {
		const firstDay = firstDayOfWeek !== void 0 ? Number(firstDayOfWeek) : void 0;
		return getWeek(date, this.locale, firstDay, firstWeekMinSize);
	}
	getDate(date) {
		return getDate(date);
	}
	getNextMonth(date) {
		return getNextMonth(date);
	}
	getPreviousMonth(date) {
		return getPreviousMonth(date);
	}
	getHours(date) {
		return getHours(date);
	}
	getMinutes(date) {
		return getMinutes(date);
	}
	startOfDay(date) {
		return startOfDay(date);
	}
	endOfDay(date) {
		return endOfDay(date);
	}
	startOfYear(date) {
		return startOfYear(date);
	}
	endOfYear(date) {
		return endOfYear(date);
	}
};
//#endregion
//#region node_modules/vuetify/lib/composables/date/date.js
var DateOptionsSymbol = Symbol.for("vuetify:date-options");
var DateAdapterSymbol = Symbol.for("vuetify:date-adapter");
function createDate(options, locale) {
	const _options = mergeDeep({
		adapter: VuetifyDateAdapter,
		locale: {
			af: "af-ZA",
			bg: "bg-BG",
			ca: "ca-ES",
			ckb: "",
			cs: "cs-CZ",
			de: "de-DE",
			el: "el-GR",
			en: "en-US",
			et: "et-EE",
			fa: "fa-IR",
			fi: "fi-FI",
			hr: "hr-HR",
			hu: "hu-HU",
			he: "he-IL",
			id: "id-ID",
			it: "it-IT",
			ja: "ja-JP",
			ko: "ko-KR",
			lv: "lv-LV",
			lt: "lt-LT",
			nl: "nl-NL",
			no: "no-NO",
			pl: "pl-PL",
			pt: "pt-PT",
			ro: "ro-RO",
			ru: "ru-RU",
			sk: "sk-SK",
			sl: "sl-SI",
			srCyrl: "sr-SP",
			srLatn: "sr-SP",
			sv: "sv-SE",
			th: "th-TH",
			tr: "tr-TR",
			az: "az-AZ",
			uk: "uk-UA",
			vi: "vi-VN",
			zhHans: "zh-CN",
			zhHant: "zh-TW"
		}
	}, options);
	return {
		options: _options,
		instance: createInstance(_options, locale)
	};
}
function createInstance(options, locale) {
	const instance = reactive(typeof options.adapter === "function" ? new options.adapter({
		locale: options.locale[locale.current.value] ?? locale.current.value,
		formats: options.formats
	}) : options.adapter);
	watch(locale.current, (value) => {
		instance.locale = options.locale[value] ?? value ?? instance.locale;
	});
	return instance;
}
function useDate() {
	const options = inject(DateOptionsSymbol);
	if (!options) throw new Error("[Vuetify] Could not find injected date options");
	return createInstance(options, useLocale());
}
//#endregion
//#region node_modules/vuetify/lib/composables/display.js
var breakpoints = [
	"sm",
	"md",
	"lg",
	"xl",
	"xxl"
];
var DisplaySymbol = Symbol.for("vuetify:display");
var defaultDisplayOptions = {
	mobileBreakpoint: "lg",
	thresholds: {
		xs: 0,
		sm: 600,
		md: 960,
		lg: 1280,
		xl: 1920,
		xxl: 2560
	}
};
var parseDisplayOptions = function() {
	return mergeDeep(defaultDisplayOptions, arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : defaultDisplayOptions);
};
function getClientWidth(ssr) {
	return IN_BROWSER && !ssr ? window.innerWidth : typeof ssr === "object" && ssr.clientWidth || 0;
}
function getClientHeight(ssr) {
	return IN_BROWSER && !ssr ? window.innerHeight : typeof ssr === "object" && ssr.clientHeight || 0;
}
function getPlatform(ssr) {
	const userAgent = IN_BROWSER && !ssr ? window.navigator.userAgent : "ssr";
	function match(regexp) {
		return Boolean(userAgent.match(regexp));
	}
	return {
		android: match(/android/i),
		ios: match(/iphone|ipad|ipod/i),
		cordova: match(/cordova/i),
		electron: match(/electron/i),
		chrome: match(/chrome/i),
		edge: match(/edge/i),
		firefox: match(/firefox/i),
		opera: match(/opera/i),
		win: match(/win/i),
		mac: match(/mac/i),
		linux: match(/linux/i),
		touch: SUPPORTS_TOUCH,
		ssr: userAgent === "ssr"
	};
}
function createDisplay(options, ssr) {
	const { thresholds, mobileBreakpoint } = parseDisplayOptions(options);
	const height = shallowRef(getClientHeight(ssr));
	const platform = shallowRef(getPlatform(ssr));
	const state = reactive({});
	const width = shallowRef(getClientWidth(ssr));
	function updateSize() {
		height.value = getClientHeight();
		width.value = getClientWidth();
	}
	function update() {
		updateSize();
		platform.value = getPlatform();
	}
	watchEffect(() => {
		const xs = width.value < thresholds.sm;
		const sm = width.value < thresholds.md && !xs;
		const md = width.value < thresholds.lg && !(sm || xs);
		const lg = width.value < thresholds.xl && !(md || sm || xs);
		const xl = width.value < thresholds.xxl && !(lg || md || sm || xs);
		const xxl = width.value >= thresholds.xxl;
		const name = xs ? "xs" : sm ? "sm" : md ? "md" : lg ? "lg" : xl ? "xl" : "xxl";
		const breakpointValue = typeof mobileBreakpoint === "number" ? mobileBreakpoint : thresholds[mobileBreakpoint];
		const mobile = width.value < breakpointValue;
		state.xs = xs;
		state.sm = sm;
		state.md = md;
		state.lg = lg;
		state.xl = xl;
		state.xxl = xxl;
		state.smAndUp = !xs;
		state.mdAndUp = !(xs || sm);
		state.lgAndUp = !(xs || sm || md);
		state.xlAndUp = !(xs || sm || md || lg);
		state.smAndDown = !(md || lg || xl || xxl);
		state.mdAndDown = !(lg || xl || xxl);
		state.lgAndDown = !(xl || xxl);
		state.xlAndDown = !xxl;
		state.name = name;
		state.height = height.value;
		state.width = width.value;
		state.mobile = mobile;
		state.mobileBreakpoint = mobileBreakpoint;
		state.platform = platform.value;
		state.thresholds = thresholds;
	});
	if (IN_BROWSER) {
		window.addEventListener("resize", updateSize, { passive: true });
		onScopeDispose(() => {
			window.removeEventListener("resize", updateSize);
		}, true);
	}
	return {
		...toRefs(state),
		update,
		ssr: !!ssr
	};
}
var makeDisplayProps = propsFactory({
	mobile: {
		type: Boolean,
		default: false
	},
	mobileBreakpoint: [Number, String]
}, "display");
function useDisplay() {
	let props = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : { mobile: null };
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	const display = inject(DisplaySymbol);
	if (!display) throw new Error("Could not find Vuetify display injection");
	const mobile = computed(() => {
		if (props.mobile) return true;
		else if (typeof props.mobileBreakpoint === "number") return display.width.value < props.mobileBreakpoint;
		else if (props.mobileBreakpoint) return display.width.value < display.thresholds.value[props.mobileBreakpoint];
		else if (props.mobile === null) return display.mobile.value;
		else return false;
	});
	const displayClasses = toRef(() => {
		if (!name) return {};
		return { [`${name}--mobile`]: mobile.value };
	});
	return {
		...display,
		displayClasses,
		mobile
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/goto.js
var GoToSymbol = Symbol.for("vuetify:goto");
function genDefaults$2() {
	return {
		container: void 0,
		duration: 300,
		layout: false,
		offset: 0,
		easing: "easeInOutCubic",
		patterns: {
			linear: (t) => t,
			easeInQuad: (t) => t ** 2,
			easeOutQuad: (t) => t * (2 - t),
			easeInOutQuad: (t) => t < .5 ? 2 * t ** 2 : -1 + (4 - 2 * t) * t,
			easeInCubic: (t) => t ** 3,
			easeOutCubic: (t) => --t ** 3 + 1,
			easeInOutCubic: (t) => t < .5 ? 4 * t ** 3 : (t - 1) * (2 * t - 2) * (2 * t - 2) + 1,
			easeInQuart: (t) => t ** 4,
			easeOutQuart: (t) => 1 - --t ** 4,
			easeInOutQuart: (t) => t < .5 ? 8 * t ** 4 : 1 - 8 * --t ** 4,
			easeInQuint: (t) => t ** 5,
			easeOutQuint: (t) => 1 + --t ** 5,
			easeInOutQuint: (t) => t < .5 ? 16 * t ** 5 : 1 + 16 * --t ** 5
		}
	};
}
function getContainer(el) {
	return getTarget$1(el) ?? (document.scrollingElement || document.body);
}
function getTarget$1(el) {
	return typeof el === "string" ? document.querySelector(el) : refElement(el);
}
function getOffset$2(target, horizontal, rtl) {
	if (typeof target === "number") return horizontal && rtl ? -target : target;
	let el = getTarget$1(target);
	let totalOffset = 0;
	while (el) {
		totalOffset += horizontal ? el.offsetLeft : el.offsetTop;
		el = el.offsetParent;
	}
	return totalOffset;
}
function createGoTo(options, locale) {
	return {
		rtl: locale.isRtl,
		options: mergeDeep(genDefaults$2(), options)
	};
}
async function scrollTo(_target, _options, horizontal, goTo) {
	const property = horizontal ? "scrollLeft" : "scrollTop";
	const options = mergeDeep(goTo?.options ?? genDefaults$2(), _options);
	const rtl = goTo?.rtl.value;
	const target = (typeof _target === "number" ? _target : getTarget$1(_target)) ?? 0;
	const container = options.container === "parent" && target instanceof HTMLElement ? target.parentElement : getContainer(options.container);
	const ease = typeof options.easing === "function" ? options.easing : options.patterns[options.easing];
	if (!ease) throw new TypeError(`Easing function "${options.easing}" not found.`);
	let targetLocation;
	if (typeof target === "number") targetLocation = getOffset$2(target, horizontal, rtl);
	else {
		targetLocation = getOffset$2(target, horizontal, rtl) - getOffset$2(container, horizontal, rtl);
		if (options.layout) {
			const layoutOffset = window.getComputedStyle(target).getPropertyValue("--v-layout-top");
			if (layoutOffset) targetLocation -= parseInt(layoutOffset, 10);
		}
	}
	targetLocation += options.offset;
	targetLocation = clampTarget(container, targetLocation, !!rtl, !!horizontal);
	const startLocation = container[property] ?? 0;
	if (targetLocation === startLocation) return Promise.resolve(targetLocation);
	const startTime = performance.now();
	return new Promise((resolve) => requestAnimationFrame(function step(currentTime) {
		const progress = (currentTime - startTime) / options.duration;
		const location = Math.floor(startLocation + (targetLocation - startLocation) * ease(clamp(progress, 0, 1)));
		container[property] = location;
		if (progress >= 1 && Math.abs(location - container[property]) < 10) return resolve(targetLocation);
		else if (progress > 2) {
			consoleWarn("Scroll target is not reachable");
			return resolve(container[property]);
		}
		requestAnimationFrame(step);
	}));
}
function useGoTo() {
	let _options = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : {};
	const goToInstance = inject(GoToSymbol);
	const { isRtl } = useRtl();
	if (!goToInstance) throw new Error("[Vuetify] Could not find injected goto instance");
	const goTo = {
		...goToInstance,
		rtl: toRef(() => goToInstance.rtl.value || isRtl.value)
	};
	async function go(target, options) {
		return scrollTo(target, mergeDeep(_options, options), false, goTo);
	}
	go.horizontal = async (target, options) => {
		return scrollTo(target, mergeDeep(_options, options), true, goTo);
	};
	return go;
}
/**
* Clamp target value to achieve a smooth scroll animation
* when the value goes outside the scroll container size
*/
function clampTarget(container, value, rtl, horizontal) {
	const { scrollWidth, scrollHeight } = container;
	const [containerWidth, containerHeight] = container === document.scrollingElement ? [window.innerWidth, window.innerHeight] : [container.offsetWidth, container.offsetHeight];
	let min;
	let max;
	if (horizontal) if (rtl) {
		min = -(scrollWidth - containerWidth);
		max = 0;
	} else {
		min = 0;
		max = scrollWidth - containerWidth;
	}
	else {
		min = 0;
		max = scrollHeight + -containerHeight;
	}
	return Math.max(Math.min(value, max), min);
}
//#endregion
//#region node_modules/vuetify/lib/iconsets/mdi.js
var aliases = {
	collapse: "mdi-chevron-up",
	complete: "mdi-check",
	cancel: "mdi-close-circle",
	close: "mdi-close",
	delete: "mdi-close-circle",
	clear: "mdi-close-circle",
	success: "mdi-check-circle",
	info: "mdi-information",
	warning: "mdi-alert-circle",
	error: "mdi-close-circle",
	prev: "mdi-chevron-left",
	next: "mdi-chevron-right",
	checkboxOn: "mdi-checkbox-marked",
	checkboxOff: "mdi-checkbox-blank-outline",
	checkboxIndeterminate: "mdi-minus-box",
	delimiter: "mdi-circle",
	sortAsc: "mdi-arrow-up",
	sortDesc: "mdi-arrow-down",
	expand: "mdi-chevron-down",
	menu: "mdi-menu",
	subgroup: "mdi-menu-down",
	dropdown: "mdi-menu-down",
	radioOn: "mdi-radiobox-marked",
	radioOff: "mdi-radiobox-blank",
	edit: "mdi-pencil",
	ratingEmpty: "mdi-star-outline",
	ratingFull: "mdi-star",
	ratingHalf: "mdi-star-half-full",
	loading: "mdi-cached",
	first: "mdi-page-first",
	last: "mdi-page-last",
	unfold: "mdi-unfold-more-horizontal",
	file: "mdi-paperclip",
	plus: "mdi-plus",
	minus: "mdi-minus",
	calendar: "mdi-calendar",
	treeviewCollapse: "mdi-menu-down",
	treeviewExpand: "mdi-menu-right",
	eyeDropper: "mdi-eyedropper",
	upload: "mdi-cloud-upload",
	color: "mdi-palette"
};
var mdi = { component: (props) => h(VClassIcon, {
	...props,
	class: "mdi"
}) };
//#endregion
//#region node_modules/vuetify/lib/composables/icons.js
var IconValue = [
	String,
	Function,
	Object,
	Array
];
var IconSymbol = Symbol.for("vuetify:icons");
var makeIconProps = propsFactory({
	icon: { type: IconValue },
	tag: {
		type: [
			String,
			Object,
			Function
		],
		required: true
	}
}, "icon");
var VComponentIcon = genericComponent()({
	name: "VComponentIcon",
	props: makeIconProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		return () => {
			const Icon = props.icon;
			return createVNode(props.tag, null, { default: () => [props.icon ? createVNode(Icon, null, null) : slots.default?.()] });
		};
	}
});
var VSvgIcon = defineComponent$1({
	name: "VSvgIcon",
	inheritAttrs: false,
	props: makeIconProps(),
	setup(props, _ref2) {
		let { attrs } = _ref2;
		return () => {
			return createVNode(props.tag, mergeProps(attrs, { "style": null }), { default: () => [createVNode("svg", {
				"class": "v-icon__svg",
				"xmlns": "http://www.w3.org/2000/svg",
				"viewBox": "0 0 24 24",
				"role": "img",
				"aria-hidden": "true"
			}, [Array.isArray(props.icon) ? props.icon.map((path) => Array.isArray(path) ? createVNode("path", {
				"d": path[0],
				"fill-opacity": path[1]
			}, null) : createVNode("path", { "d": path }, null)) : createVNode("path", { "d": props.icon }, null)])] });
		};
	}
});
var VLigatureIcon = defineComponent$1({
	name: "VLigatureIcon",
	props: makeIconProps(),
	setup(props) {
		return () => {
			return createVNode(props.tag, null, { default: () => [props.icon] });
		};
	}
});
var VClassIcon = defineComponent$1({
	name: "VClassIcon",
	props: makeIconProps(),
	setup(props) {
		return () => {
			return createVNode(props.tag, { "class": props.icon }, null);
		};
	}
});
function genDefaults$1() {
	return {
		svg: { component: VSvgIcon },
		class: { component: VClassIcon }
	};
}
function createIcons(options) {
	const sets = genDefaults$1();
	const defaultSet = options?.defaultSet ?? "mdi";
	if (defaultSet === "mdi" && !sets.mdi) sets.mdi = mdi;
	return mergeDeep({
		defaultSet,
		sets,
		aliases: {
			...aliases,
			vuetify: ["M8.2241 14.2009L12 21L22 3H14.4459L8.2241 14.2009Z", ["M7.26303 12.4733L7.00113 12L2 3H12.5261C12.5261 3 12.5261 3 12.5261 3L7.26303 12.4733Z", .6]],
			"vuetify-outline": "svg:M7.26 12.47 12.53 3H2L7.26 12.47ZM14.45 3 8.22 14.2 12 21 22 3H14.45ZM18.6 5 12 16.88 10.51 14.2 15.62 5ZM7.26 8.35 5.4 5H9.13L7.26 8.35Z",
			"vuetify-play": ["m6.376 13.184-4.11-7.192C1.505 4.66 2.467 3 4.003 3h8.532l-.953 1.576-.006.01-.396.677c-.429.732-.214 1.507.194 2.015.404.503 1.092.878 1.869.806a3.72 3.72 0 0 1 1.005.022c.276.053.434.143.523.237.138.146.38.635-.25 2.09-.893 1.63-1.553 1.722-1.847 1.677-.213-.033-.468-.158-.756-.406a4.95 4.95 0 0 1-.8-.927c-.39-.564-1.04-.84-1.66-.846-.625-.006-1.316.27-1.693.921l-.478.826-.911 1.506Z", ["M9.093 11.552c.046-.079.144-.15.32-.148a.53.53 0 0 1 .43.207c.285.414.636.847 1.046 1.2.405.35.914.662 1.516.754 1.334.205 2.502-.698 3.48-2.495l.014-.028.013-.03c.687-1.574.774-2.852-.005-3.675-.37-.391-.861-.586-1.333-.676a5.243 5.243 0 0 0-1.447-.044c-.173.016-.393-.073-.54-.257-.145-.18-.127-.316-.082-.392l.393-.672L14.287 3h5.71c1.536 0 2.499 1.659 1.737 2.992l-7.997 13.996c-.768 1.344-2.706 1.344-3.473 0l-3.037-5.314 1.377-2.278.004-.006.004-.007.481-.831Z", .6]]
		}
	}, options);
}
var useIcon = (props) => {
	const icons = inject(IconSymbol);
	if (!icons) throw new Error("Missing Vuetify Icons provide!");
	return { iconData: computed(() => {
		const iconAlias = toValue(props);
		if (!iconAlias) return { component: VComponentIcon };
		let icon = iconAlias;
		if (typeof icon === "string") {
			icon = icon.trim();
			if (icon.startsWith("$")) icon = icons.aliases?.[icon.slice(1)];
		}
		if (!icon) consoleWarn(`Could not find aliased icon "${iconAlias}"`);
		if (Array.isArray(icon)) return {
			component: VSvgIcon,
			icon
		};
		else if (typeof icon !== "string") return {
			component: VComponentIcon,
			icon
		};
		const iconSetName = Object.keys(icons.sets).find((setName) => typeof icon === "string" && icon.startsWith(`${setName}:`));
		const iconName = iconSetName ? icon.slice(iconSetName.length + 1) : icon;
		return {
			component: icons.sets[iconSetName ?? icons.defaultSet].component,
			icon: iconName
		};
	}) };
};
//#endregion
//#region node_modules/vuetify/lib/composables/theme.js
var ThemeSymbol = Symbol.for("vuetify:theme");
var makeThemeProps = propsFactory({ theme: String }, "theme");
function genDefaults() {
	return {
		defaultTheme: "light",
		variations: {
			colors: [],
			lighten: 0,
			darken: 0
		},
		themes: {
			light: {
				dark: false,
				colors: {
					background: "#FFFFFF",
					surface: "#FFFFFF",
					"surface-bright": "#FFFFFF",
					"surface-light": "#EEEEEE",
					"surface-variant": "#424242",
					"on-surface-variant": "#EEEEEE",
					primary: "#1867C0",
					"primary-darken-1": "#1F5592",
					secondary: "#48A9A6",
					"secondary-darken-1": "#018786",
					error: "#B00020",
					info: "#2196F3",
					success: "#4CAF50",
					warning: "#FB8C00"
				},
				variables: {
					"border-color": "#000000",
					"border-opacity": .12,
					"high-emphasis-opacity": .87,
					"medium-emphasis-opacity": .6,
					"disabled-opacity": .38,
					"idle-opacity": .04,
					"hover-opacity": .04,
					"focus-opacity": .12,
					"selected-opacity": .08,
					"activated-opacity": .12,
					"pressed-opacity": .12,
					"dragged-opacity": .08,
					"theme-kbd": "#212529",
					"theme-on-kbd": "#FFFFFF",
					"theme-code": "#F5F5F5",
					"theme-on-code": "#000000"
				}
			},
			dark: {
				dark: true,
				colors: {
					background: "#121212",
					surface: "#212121",
					"surface-bright": "#ccbfd6",
					"surface-light": "#424242",
					"surface-variant": "#c8c8c8",
					"on-surface-variant": "#000000",
					primary: "#2196F3",
					"primary-darken-1": "#277CC1",
					secondary: "#54B6B2",
					"secondary-darken-1": "#48A9A6",
					error: "#CF6679",
					info: "#2196F3",
					success: "#4CAF50",
					warning: "#FB8C00"
				},
				variables: {
					"border-color": "#FFFFFF",
					"border-opacity": .12,
					"high-emphasis-opacity": 1,
					"medium-emphasis-opacity": .7,
					"disabled-opacity": .5,
					"idle-opacity": .1,
					"hover-opacity": .04,
					"focus-opacity": .12,
					"selected-opacity": .08,
					"activated-opacity": .12,
					"pressed-opacity": .16,
					"dragged-opacity": .08,
					"theme-kbd": "#212529",
					"theme-on-kbd": "#FFFFFF",
					"theme-code": "#343434",
					"theme-on-code": "#CCCCCC"
				}
			}
		},
		stylesheetId: "vuetify-theme-stylesheet"
	};
}
function parseThemeOptions() {
	let options = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : genDefaults();
	const defaults = genDefaults();
	if (!options) return {
		...defaults,
		isDisabled: true
	};
	const themes = {};
	for (const [key, theme] of Object.entries(options.themes ?? {})) themes[key] = mergeDeep(theme.dark || key === "dark" ? defaults.themes?.dark : defaults.themes?.light, theme);
	return mergeDeep(defaults, {
		...options,
		themes
	});
}
function createCssClass(lines, selector, content, scope) {
	lines.push(`${getScopedSelector(selector, scope)} {\n`, ...content.map((line) => `  ${line};\n`), "}\n");
}
function genCssVariables(theme) {
	const lightOverlay = theme.dark ? 2 : 1;
	const darkOverlay = theme.dark ? 1 : 2;
	const variables = [];
	for (const [key, value] of Object.entries(theme.colors)) {
		const rgb = parseColor(value);
		variables.push(`--v-theme-${key}: ${rgb.r},${rgb.g},${rgb.b}`);
		if (!key.startsWith("on-")) variables.push(`--v-theme-${key}-overlay-multiplier: ${getLuma(value) > .18 ? lightOverlay : darkOverlay}`);
	}
	for (const [key, value] of Object.entries(theme.variables)) {
		const color = typeof value === "string" && value.startsWith("#") ? parseColor(value) : void 0;
		const rgb = color ? `${color.r}, ${color.g}, ${color.b}` : void 0;
		variables.push(`--v-${key}: ${rgb ?? value}`);
	}
	return variables;
}
function genVariation(name, color, variations) {
	const object = {};
	if (variations) for (const variation of ["lighten", "darken"]) {
		const fn = variation === "lighten" ? lighten : darken;
		for (const amount of createRange(variations[variation], 1)) object[`${name}-${variation}-${amount}`] = RGBtoHex(fn(parseColor(color), amount));
	}
	return object;
}
function genVariations(colors, variations) {
	if (!variations) return {};
	let variationColors = {};
	for (const name of variations.colors) {
		const color = colors[name];
		if (!color) continue;
		variationColors = {
			...variationColors,
			...genVariation(name, color, variations)
		};
	}
	return variationColors;
}
function genOnColors(colors) {
	const onColors = {};
	for (const color of Object.keys(colors)) {
		if (color.startsWith("on-") || colors[`on-${color}`]) continue;
		const onColor = `on-${color}`;
		onColors[onColor] = getForeground(parseColor(colors[color]));
	}
	return onColors;
}
function getScopedSelector(selector, scope) {
	if (!scope) return selector;
	const scopeSelector = `:where(${scope})`;
	return selector === ":root" ? scopeSelector : `${scopeSelector} ${selector}`;
}
function upsertStyles(styleEl, styles) {
	if (!styleEl) return;
	styleEl.innerHTML = styles;
}
function getOrCreateStyleElement(id, cspNonce) {
	if (!IN_BROWSER) return null;
	let style = document.getElementById(id);
	if (!style) {
		style = document.createElement("style");
		style.id = id;
		style.type = "text/css";
		if (cspNonce) style.setAttribute("nonce", cspNonce);
		document.head.appendChild(style);
	}
	return style;
}
function createTheme(options) {
	const parsedOptions = parseThemeOptions(options);
	const name = shallowRef(parsedOptions.defaultTheme);
	const themes = ref(parsedOptions.themes);
	const computedThemes = computed(() => {
		const acc = {};
		for (const [name, original] of Object.entries(themes.value)) {
			const colors = {
				...original.colors,
				...genVariations(original.colors, parsedOptions.variations)
			};
			acc[name] = {
				...original,
				colors: {
					...colors,
					...genOnColors(colors)
				}
			};
		}
		return acc;
	});
	const current = toRef(() => computedThemes.value[name.value]);
	const styles = computed(() => {
		const lines = [];
		if (current.value?.dark) createCssClass(lines, ":root", ["color-scheme: dark"], parsedOptions.scope);
		createCssClass(lines, ":root", genCssVariables(current.value), parsedOptions.scope);
		for (const [themeName, theme] of Object.entries(computedThemes.value)) createCssClass(lines, `.v-theme--${themeName}`, [`color-scheme: ${theme.dark ? "dark" : "normal"}`, ...genCssVariables(theme)], parsedOptions.scope);
		const bgLines = [];
		const fgLines = [];
		const colors = new Set(Object.values(computedThemes.value).flatMap((theme) => Object.keys(theme.colors)));
		for (const key of colors) if (key.startsWith("on-")) createCssClass(fgLines, `.${key}`, [`color: rgb(var(--v-theme-${key})) !important`], parsedOptions.scope);
		else {
			createCssClass(bgLines, `.bg-${key}`, [
				`--v-theme-overlay-multiplier: var(--v-theme-${key}-overlay-multiplier)`,
				`background-color: rgb(var(--v-theme-${key})) !important`,
				`color: rgb(var(--v-theme-on-${key})) !important`
			], parsedOptions.scope);
			createCssClass(fgLines, `.text-${key}`, [`color: rgb(var(--v-theme-${key})) !important`], parsedOptions.scope);
			createCssClass(fgLines, `.border-${key}`, [`--v-border-color: var(--v-theme-${key})`], parsedOptions.scope);
		}
		lines.push(...bgLines, ...fgLines);
		return lines.map((str, i) => i === 0 ? str : `    ${str}`).join("");
	});
	function install(app) {
		if (parsedOptions.isDisabled) return;
		const head = app._context.provides.usehead;
		if (head) {
			function getHead() {
				return { style: [{
					textContent: styles.value,
					id: parsedOptions.stylesheetId,
					nonce: parsedOptions.cspNonce || false
				}] };
			}
			if (head.push) {
				const entry = head.push(getHead);
				if (IN_BROWSER) watch(styles, () => {
					entry.patch(getHead);
				});
			} else if (IN_BROWSER) {
				head.addHeadObjs(toRef(getHead));
				watchEffect(() => head.updateDOM());
			} else head.addHeadObjs(getHead());
		} else {
			if (IN_BROWSER) watch(styles, updateStyles, { immediate: true });
			else updateStyles();
			function updateStyles() {
				upsertStyles(getOrCreateStyleElement(parsedOptions.stylesheetId, parsedOptions.cspNonce), styles.value);
			}
		}
	}
	const themeClasses = toRef(() => parsedOptions.isDisabled ? void 0 : `v-theme--${name.value}`);
	return {
		install,
		isDisabled: parsedOptions.isDisabled,
		name,
		themes,
		current,
		computedThemes,
		themeClasses,
		styles,
		global: {
			name,
			current
		}
	};
}
function provideTheme(props) {
	getCurrentInstance$1("provideTheme");
	const theme = inject(ThemeSymbol, null);
	if (!theme) throw new Error("Could not find Vuetify theme injection");
	const name = toRef(() => props.theme ?? theme.name.value);
	const current = toRef(() => theme.themes.value[name.value]);
	const themeClasses = toRef(() => theme.isDisabled ? void 0 : `v-theme--${name.value}`);
	const newTheme = {
		...theme,
		name,
		current,
		themeClasses
	};
	provide(ThemeSymbol, newTheme);
	return newTheme;
}
function useTheme() {
	getCurrentInstance$1("useTheme");
	const theme = inject(ThemeSymbol, null);
	if (!theme) throw new Error("Could not find Vuetify theme injection");
	return theme;
}
//#endregion
//#region node_modules/vuetify/lib/composables/resizeObserver.js
function useResizeObserver(callback) {
	let box = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : "content";
	const resizeRef = templateRef();
	const contentRect = ref();
	if (IN_BROWSER) {
		const observer = new ResizeObserver((entries) => {
			callback?.(entries, observer);
			if (!entries.length) return;
			if (box === "content") contentRect.value = entries[0].contentRect;
			else contentRect.value = entries[0].target.getBoundingClientRect();
		});
		onBeforeUnmount(() => {
			observer.disconnect();
		});
		watch(() => resizeRef.el, (newValue, oldValue) => {
			if (oldValue) {
				observer.unobserve(oldValue);
				contentRect.value = void 0;
			}
			if (newValue) observer.observe(newValue);
		}, { flush: "post" });
	}
	return {
		resizeRef,
		contentRect: readonly(contentRect)
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/layout.js
var VuetifyLayoutKey = Symbol.for("vuetify:layout");
var VuetifyLayoutItemKey = Symbol.for("vuetify:layout-item");
var ROOT_ZINDEX = 1e3;
var makeLayoutProps = propsFactory({
	overlaps: {
		type: Array,
		default: () => []
	},
	fullHeight: Boolean
}, "layout");
var makeLayoutItemProps = propsFactory({
	name: { type: String },
	order: {
		type: [Number, String],
		default: 0
	},
	absolute: Boolean
}, "layout-item");
function useLayout() {
	const layout = inject(VuetifyLayoutKey);
	if (!layout) throw new Error("[Vuetify] Could not find injected layout");
	return {
		getLayoutItem: layout.getLayoutItem,
		mainRect: layout.mainRect,
		mainStyles: layout.mainStyles
	};
}
function useLayoutItem(options) {
	const layout = inject(VuetifyLayoutKey);
	if (!layout) throw new Error("[Vuetify] Could not find injected layout");
	const id = options.id ?? `layout-item-${useId()}`;
	const vm = getCurrentInstance$1("useLayoutItem");
	provide(VuetifyLayoutItemKey, { id });
	const isKeptAlive = shallowRef(false);
	onDeactivated(() => isKeptAlive.value = true);
	onActivated(() => isKeptAlive.value = false);
	const { layoutItemStyles, layoutItemScrimStyles } = layout.register(vm, {
		...options,
		active: computed(() => isKeptAlive.value ? false : options.active.value),
		id
	});
	onBeforeUnmount(() => layout.unregister(id));
	return {
		layoutItemStyles,
		layoutRect: layout.layoutRect,
		layoutItemScrimStyles
	};
}
var generateLayers = (layout, positions, layoutSizes, activeItems) => {
	let previousLayer = {
		top: 0,
		left: 0,
		right: 0,
		bottom: 0
	};
	const layers = [{
		id: "",
		layer: { ...previousLayer }
	}];
	for (const id of layout) {
		const position = positions.get(id);
		const amount = layoutSizes.get(id);
		const active = activeItems.get(id);
		if (!position || !amount || !active) continue;
		const layer = {
			...previousLayer,
			[position.value]: parseInt(previousLayer[position.value], 10) + (active.value ? parseInt(amount.value, 10) : 0)
		};
		layers.push({
			id,
			layer
		});
		previousLayer = layer;
	}
	return layers;
};
function createLayout(props) {
	const parentLayout = inject(VuetifyLayoutKey, null);
	const rootZIndex = computed(() => parentLayout ? parentLayout.rootZIndex.value - 100 : ROOT_ZINDEX);
	const registered = ref([]);
	const positions = reactive(/* @__PURE__ */ new Map());
	const layoutSizes = reactive(/* @__PURE__ */ new Map());
	const priorities = reactive(/* @__PURE__ */ new Map());
	const activeItems = reactive(/* @__PURE__ */ new Map());
	const disabledTransitions = reactive(/* @__PURE__ */ new Map());
	const { resizeRef, contentRect: layoutRect } = useResizeObserver();
	const computedOverlaps = computed(() => {
		const map = /* @__PURE__ */ new Map();
		const overlaps = props.overlaps ?? [];
		for (const overlap of overlaps.filter((item) => item.includes(":"))) {
			const [top, bottom] = overlap.split(":");
			if (!registered.value.includes(top) || !registered.value.includes(bottom)) continue;
			const topPosition = positions.get(top);
			const bottomPosition = positions.get(bottom);
			const topAmount = layoutSizes.get(top);
			const bottomAmount = layoutSizes.get(bottom);
			if (!topPosition || !bottomPosition || !topAmount || !bottomAmount) continue;
			map.set(bottom, {
				position: topPosition.value,
				amount: parseInt(topAmount.value, 10)
			});
			map.set(top, {
				position: bottomPosition.value,
				amount: -parseInt(bottomAmount.value, 10)
			});
		}
		return map;
	});
	const layers = computed(() => {
		const uniquePriorities = [...new Set([...priorities.values()].map((p) => p.value))].sort((a, b) => a - b);
		const layout = [];
		for (const p of uniquePriorities) {
			const items = registered.value.filter((id) => priorities.get(id)?.value === p);
			layout.push(...items);
		}
		return generateLayers(layout, positions, layoutSizes, activeItems);
	});
	const transitionsEnabled = computed(() => {
		return !Array.from(disabledTransitions.values()).some((ref) => ref.value);
	});
	const mainRect = computed(() => {
		return layers.value[layers.value.length - 1].layer;
	});
	const mainStyles = toRef(() => {
		return {
			"--v-layout-left": convertToUnit(mainRect.value.left),
			"--v-layout-right": convertToUnit(mainRect.value.right),
			"--v-layout-top": convertToUnit(mainRect.value.top),
			"--v-layout-bottom": convertToUnit(mainRect.value.bottom),
			...transitionsEnabled.value ? void 0 : { transition: "none" }
		};
	});
	const items = computed(() => {
		return layers.value.slice(1).map((_ref, index) => {
			let { id } = _ref;
			const { layer } = layers.value[index];
			const size = layoutSizes.get(id);
			const position = positions.get(id);
			return {
				id,
				...layer,
				size: Number(size.value),
				position: position.value
			};
		});
	});
	const getLayoutItem = (id) => {
		return items.value.find((item) => item.id === id);
	};
	const rootVm = getCurrentInstance$1("createLayout");
	const isMounted = shallowRef(false);
	onMounted(() => {
		isMounted.value = true;
	});
	provide(VuetifyLayoutKey, {
		register: (vm, _ref2) => {
			let { id, order, position, layoutSize, elementSize, active, disableTransitions, absolute } = _ref2;
			priorities.set(id, order);
			positions.set(id, position);
			layoutSizes.set(id, layoutSize);
			activeItems.set(id, active);
			disableTransitions && disabledTransitions.set(id, disableTransitions);
			const instanceIndex = findChildrenWithProvide(VuetifyLayoutItemKey, rootVm?.vnode).indexOf(vm);
			if (instanceIndex > -1) registered.value.splice(instanceIndex, 0, id);
			else registered.value.push(id);
			const index = computed(() => items.value.findIndex((i) => i.id === id));
			const zIndex = computed(() => rootZIndex.value + layers.value.length * 2 - index.value * 2);
			return {
				layoutItemStyles: computed(() => {
					const isHorizontal = position.value === "left" || position.value === "right";
					const isOppositeHorizontal = position.value === "right";
					const isOppositeVertical = position.value === "bottom";
					const size = elementSize.value ?? layoutSize.value;
					const unit = size === 0 ? "%" : "px";
					const styles = {
						[position.value]: 0,
						zIndex: zIndex.value,
						transform: `translate${isHorizontal ? "X" : "Y"}(${(active.value ? 0 : -(size === 0 ? 100 : size)) * (isOppositeHorizontal || isOppositeVertical ? -1 : 1)}${unit})`,
						position: absolute.value || rootZIndex.value !== ROOT_ZINDEX ? "absolute" : "fixed",
						...transitionsEnabled.value ? void 0 : { transition: "none" }
					};
					if (!isMounted.value) return styles;
					const item = items.value[index.value];
					if (!item) throw new Error(`[Vuetify] Could not find layout item "${id}"`);
					const overlap = computedOverlaps.value.get(id);
					if (overlap) item[overlap.position] += overlap.amount;
					return {
						...styles,
						height: isHorizontal ? `calc(100% - ${item.top}px - ${item.bottom}px)` : elementSize.value ? `${elementSize.value}px` : void 0,
						left: isOppositeHorizontal ? void 0 : `${item.left}px`,
						right: isOppositeHorizontal ? `${item.right}px` : void 0,
						top: position.value !== "bottom" ? `${item.top}px` : void 0,
						bottom: position.value !== "top" ? `${item.bottom}px` : void 0,
						width: !isHorizontal ? `calc(100% - ${item.left}px - ${item.right}px)` : elementSize.value ? `${elementSize.value}px` : void 0
					};
				}),
				layoutItemScrimStyles: computed(() => ({ zIndex: zIndex.value - 1 })),
				zIndex
			};
		},
		unregister: (id) => {
			priorities.delete(id);
			positions.delete(id);
			layoutSizes.delete(id);
			activeItems.delete(id);
			disabledTransitions.delete(id);
			registered.value = registered.value.filter((v) => v !== id);
		},
		mainRect,
		mainStyles,
		getLayoutItem,
		items,
		layoutRect,
		rootZIndex
	});
	return {
		layoutClasses: toRef(() => ["v-layout", { "v-layout--full-height": props.fullHeight }]),
		layoutStyles: toRef(() => ({
			zIndex: parentLayout ? rootZIndex.value : void 0,
			position: parentLayout ? "relative" : void 0,
			overflow: parentLayout ? "hidden" : void 0
		})),
		getLayoutItem,
		items,
		layoutRect,
		layoutRef: resizeRef
	};
}
//#endregion
//#region node_modules/vuetify/lib/framework.js
function createVuetify() {
	const { blueprint, ...rest } = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : {};
	const options = mergeDeep(blueprint, rest);
	const { aliases = {}, components = {}, directives = {} } = options;
	const scope = effectScope();
	return scope.run(() => {
		const defaults = createDefaults(options.defaults);
		const display = createDisplay(options.display, options.ssr);
		const theme = createTheme(options.theme);
		const icons = createIcons(options.icons);
		const locale = createLocale(options.locale);
		const date = createDate(options.date, locale);
		const goTo = createGoTo(options.goTo, locale);
		function install(app) {
			for (const key in directives) app.directive(key, directives[key]);
			for (const key in components) app.component(key, components[key]);
			for (const key in aliases) app.component(key, defineComponent$1({
				...aliases[key],
				name: key,
				aliasName: aliases[key].name
			}));
			const appScope = effectScope();
			appScope.run(() => {
				theme.install(app);
			});
			app.onUnmount(() => appScope.stop());
			app.provide(DefaultsSymbol, defaults);
			app.provide(DisplaySymbol, display);
			app.provide(ThemeSymbol, theme);
			app.provide(IconSymbol, icons);
			app.provide(LocaleSymbol, locale);
			app.provide(DateOptionsSymbol, date.options);
			app.provide(DateAdapterSymbol, date.instance);
			app.provide(GoToSymbol, goTo);
			if (IN_BROWSER && options.ssr) if (app.$nuxt) app.$nuxt.hook("app:suspense:resolve", () => {
				display.update();
			});
			else {
				const { mount } = app;
				app.mount = function() {
					const vm = mount(...arguments);
					nextTick(() => display.update());
					app.mount = mount;
					return vm;
				};
			}
			app.mixin({ computed: { $vuetify() {
				return reactive({
					defaults: inject$1.call(this, DefaultsSymbol),
					display: inject$1.call(this, DisplaySymbol),
					theme: inject$1.call(this, ThemeSymbol),
					icons: inject$1.call(this, IconSymbol),
					locale: inject$1.call(this, LocaleSymbol),
					date: inject$1.call(this, DateAdapterSymbol)
				});
			} } });
		}
		function unmount() {
			scope.stop();
		}
		return {
			install,
			unmount,
			defaults,
			display,
			theme,
			icons,
			locale,
			date,
			goTo
		};
	});
}
createVuetify.version = "3.8.5";
function inject$1(key) {
	const vm = this.$;
	const provides = vm.parent?.provides ?? vm.vnode.appContext?.provides;
	if (provides && key in provides) return provides[key];
}
//#endregion
//#region node_modules/vuetify/lib/components/VApp/VApp.js
var makeVAppProps = propsFactory({
	...makeComponentProps(),
	...makeLayoutProps({ fullHeight: true }),
	...makeThemeProps()
}, "VApp");
var VApp = genericComponent()({
	name: "VApp",
	props: makeVAppProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const theme = provideTheme(props);
		const { layoutClasses, getLayoutItem, items, layoutRef } = createLayout(props);
		const { rtlClasses } = useRtl();
		useRender(() => createVNode("div", {
			"ref": layoutRef,
			"class": [
				"v-application",
				theme.themeClasses.value,
				layoutClasses.value,
				rtlClasses.value,
				props.class
			],
			"style": [props.style]
		}, [createVNode("div", { "class": "v-application__wrap" }, [slots.default?.()])]));
		return {
			getLayoutItem,
			items,
			theme
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/tag.js
var makeTagProps = propsFactory({ tag: {
	type: [
		String,
		Object,
		Function
	],
	default: "div"
} }, "tag");
//#endregion
//#region node_modules/vuetify/lib/components/VToolbar/VToolbarTitle.js
var makeVToolbarTitleProps = propsFactory({
	text: String,
	...makeComponentProps(),
	...makeTagProps()
}, "VToolbarTitle");
var VToolbarTitle = genericComponent()({
	name: "VToolbarTitle",
	props: makeVToolbarTitleProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => {
			const hasText = !!(slots.default || slots.text || props.text);
			return createVNode(props.tag, {
				"class": ["v-toolbar-title", props.class],
				"style": props.style
			}, { default: () => [hasText && createVNode("div", { "class": "v-toolbar-title__placeholder" }, [slots.text ? slots.text() : props.text, slots.default?.()])] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/transitions/createTransition.js
var makeTransitionProps$1 = propsFactory({
	disabled: Boolean,
	group: Boolean,
	hideOnLeave: Boolean,
	leaveAbsolute: Boolean,
	mode: String,
	origin: String
}, "transition");
function createCssTransition(name, origin, mode) {
	return genericComponent()({
		name,
		props: makeTransitionProps$1({
			mode,
			origin
		}),
		setup(props, _ref) {
			let { slots } = _ref;
			const functions = {
				onBeforeEnter(el) {
					if (props.origin) el.style.transformOrigin = props.origin;
				},
				onLeave(el) {
					if (props.leaveAbsolute) {
						const { offsetTop, offsetLeft, offsetWidth, offsetHeight } = el;
						el._transitionInitialStyles = {
							position: el.style.position,
							top: el.style.top,
							left: el.style.left,
							width: el.style.width,
							height: el.style.height
						};
						el.style.position = "absolute";
						el.style.top = `${offsetTop}px`;
						el.style.left = `${offsetLeft}px`;
						el.style.width = `${offsetWidth}px`;
						el.style.height = `${offsetHeight}px`;
					}
					if (props.hideOnLeave) el.style.setProperty("display", "none", "important");
				},
				onAfterLeave(el) {
					if (props.leaveAbsolute && el?._transitionInitialStyles) {
						const { position, top, left, width, height } = el._transitionInitialStyles;
						delete el._transitionInitialStyles;
						el.style.position = position || "";
						el.style.top = top || "";
						el.style.left = left || "";
						el.style.width = width || "";
						el.style.height = height || "";
					}
				}
			};
			return () => {
				return h(props.group ? TransitionGroup : Transition, {
					name: props.disabled ? "" : name,
					css: !props.disabled,
					...props.group ? void 0 : { mode: props.mode },
					...props.disabled ? {} : functions
				}, slots.default);
			};
		}
	});
}
function createJavascriptTransition(name, functions) {
	let mode = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : "in-out";
	return genericComponent()({
		name,
		props: {
			mode: {
				type: String,
				default: mode
			},
			disabled: Boolean,
			group: Boolean
		},
		setup(props, _ref2) {
			let { slots } = _ref2;
			const tag = props.group ? TransitionGroup : Transition;
			return () => {
				return h(tag, {
					name: props.disabled ? "" : name,
					css: !props.disabled,
					...props.disabled ? {} : functions
				}, slots.default);
			};
		}
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/transitions/expand-transition.js
function expand_transition_default() {
	let expandedParentClass = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : "";
	const sizeProperty = (arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : false) ? "width" : "height";
	const offsetProperty = camelize(`offset-${sizeProperty}`);
	return {
		onBeforeEnter(el) {
			el._parent = el.parentNode;
			el._initialStyle = {
				transition: el.style.transition,
				overflow: el.style.overflow,
				[sizeProperty]: el.style[sizeProperty]
			};
		},
		onEnter(el) {
			const initialStyle = el._initialStyle;
			if (!initialStyle) return;
			el.style.setProperty("transition", "none", "important");
			el.style.overflow = "hidden";
			const offset = `${el[offsetProperty]}px`;
			el.style[sizeProperty] = "0";
			el.offsetHeight;
			el.style.transition = initialStyle.transition;
			if (expandedParentClass && el._parent) el._parent.classList.add(expandedParentClass);
			requestAnimationFrame(() => {
				el.style[sizeProperty] = offset;
			});
		},
		onAfterEnter: resetStyles,
		onEnterCancelled: resetStyles,
		onLeave(el) {
			el._initialStyle = {
				transition: "",
				overflow: el.style.overflow,
				[sizeProperty]: el.style[sizeProperty]
			};
			el.style.overflow = "hidden";
			el.style[sizeProperty] = `${el[offsetProperty]}px`;
			el.offsetHeight;
			requestAnimationFrame(() => el.style[sizeProperty] = "0");
		},
		onAfterLeave,
		onLeaveCancelled: onAfterLeave
	};
	function onAfterLeave(el) {
		if (expandedParentClass && el._parent) el._parent.classList.remove(expandedParentClass);
		resetStyles(el);
	}
	function resetStyles(el) {
		if (!el._initialStyle) return;
		const size = el._initialStyle[sizeProperty];
		el.style.overflow = el._initialStyle.overflow;
		if (size != null) el.style[sizeProperty] = size;
		delete el._initialStyle;
	}
}
//#endregion
//#region node_modules/vuetify/lib/components/transitions/dialog-transition.js
var makeVDialogTransitionProps = propsFactory({ target: [Object, Array] }, "v-dialog-transition");
var saved = /* @__PURE__ */ new WeakMap();
var VDialogTransition = genericComponent()({
	name: "VDialogTransition",
	props: makeVDialogTransitionProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const functions = {
			onBeforeEnter(el) {
				el.style.pointerEvents = "none";
				el.style.visibility = "hidden";
			},
			async onEnter(el, done) {
				await new Promise((resolve) => requestAnimationFrame(resolve));
				await new Promise((resolve) => requestAnimationFrame(resolve));
				el.style.visibility = "";
				const dimensions = getDimensions(props.target, el);
				const { x, y, sx, sy, speed } = dimensions;
				saved.set(el, dimensions);
				const animation = animate(el, [{
					transform: `translate(${x}px, ${y}px) scale(${sx}, ${sy})`,
					opacity: 0
				}, {}], {
					duration: 225 * speed,
					easing: deceleratedEasing
				});
				getChildren(el)?.forEach((el) => {
					animate(el, [
						{ opacity: 0 },
						{
							opacity: 0,
							offset: .33
						},
						{}
					], {
						duration: 450 * speed,
						easing: standardEasing
					});
				});
				animation.finished.then(() => done());
			},
			onAfterEnter(el) {
				el.style.removeProperty("pointer-events");
			},
			onBeforeLeave(el) {
				el.style.pointerEvents = "none";
			},
			async onLeave(el, done) {
				await new Promise((resolve) => requestAnimationFrame(resolve));
				let dimensions;
				if (!saved.has(el) || Array.isArray(props.target) || props.target.offsetParent || props.target.getClientRects().length) dimensions = getDimensions(props.target, el);
				else dimensions = saved.get(el);
				const { x, y, sx, sy, speed } = dimensions;
				animate(el, [{}, {
					transform: `translate(${x}px, ${y}px) scale(${sx}, ${sy})`,
					opacity: 0
				}], {
					duration: 125 * speed,
					easing: acceleratedEasing
				}).finished.then(() => done());
				getChildren(el)?.forEach((el) => {
					animate(el, [
						{},
						{
							opacity: 0,
							offset: .2
						},
						{ opacity: 0 }
					], {
						duration: 250 * speed,
						easing: standardEasing
					});
				});
			},
			onAfterLeave(el) {
				el.style.removeProperty("pointer-events");
			}
		};
		return () => {
			return props.target ? createVNode(Transition, mergeProps({ "name": "dialog-transition" }, functions, { "css": false }), slots) : createVNode(Transition, { "name": "dialog-transition" }, slots);
		};
	}
});
/** Animatable children (card, sheet, list) */
function getChildren(el) {
	const els = el.querySelector(":scope > .v-card, :scope > .v-sheet, :scope > .v-list")?.children;
	return els && [...els];
}
function getDimensions(target, el) {
	const targetBox = getTargetBox(target);
	const elBox = nullifyTransforms(el);
	const [originX, originY] = getComputedStyle(el).transformOrigin.split(" ").map((v) => parseFloat(v));
	const [anchorSide, anchorOffset] = getComputedStyle(el).getPropertyValue("--v-overlay-anchor-origin").split(" ");
	let offsetX = targetBox.left + targetBox.width / 2;
	if (anchorSide === "left" || anchorOffset === "left") offsetX -= targetBox.width / 2;
	else if (anchorSide === "right" || anchorOffset === "right") offsetX += targetBox.width / 2;
	let offsetY = targetBox.top + targetBox.height / 2;
	if (anchorSide === "top" || anchorOffset === "top") offsetY -= targetBox.height / 2;
	else if (anchorSide === "bottom" || anchorOffset === "bottom") offsetY += targetBox.height / 2;
	const tsx = targetBox.width / elBox.width;
	const tsy = targetBox.height / elBox.height;
	const maxs = Math.max(1, tsx, tsy);
	const sx = tsx / maxs || 0;
	const sy = tsy / maxs || 0;
	const asa = elBox.width * elBox.height / (window.innerWidth * window.innerHeight);
	const speed = asa > .12 ? Math.min(1.5, (asa - .12) * 10 + 1) : 1;
	return {
		x: offsetX - (originX + elBox.left),
		y: offsetY - (originY + elBox.top),
		sx,
		sy,
		speed
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/transitions/index.js
var VFabTransition = createCssTransition("fab-transition", "center center", "out-in");
var VDialogBottomTransition = createCssTransition("dialog-bottom-transition");
var VDialogTopTransition = createCssTransition("dialog-top-transition");
var VFadeTransition = createCssTransition("fade-transition");
var VScaleTransition = createCssTransition("scale-transition");
var VScrollXTransition = createCssTransition("scroll-x-transition");
var VScrollXReverseTransition = createCssTransition("scroll-x-reverse-transition");
var VScrollYTransition = createCssTransition("scroll-y-transition");
var VScrollYReverseTransition = createCssTransition("scroll-y-reverse-transition");
var VSlideXTransition = createCssTransition("slide-x-transition");
var VSlideXReverseTransition = createCssTransition("slide-x-reverse-transition");
var VSlideYTransition = createCssTransition("slide-y-transition");
var VSlideYReverseTransition = createCssTransition("slide-y-reverse-transition");
var VExpandTransition = createJavascriptTransition("expand-transition", expand_transition_default());
var VExpandXTransition = createJavascriptTransition("expand-x-transition", expand_transition_default("", true));
//#endregion
//#region node_modules/vuetify/lib/components/VDefaultsProvider/VDefaultsProvider.js
var makeVDefaultsProviderProps = propsFactory({
	defaults: Object,
	disabled: Boolean,
	reset: [Number, String],
	root: [Boolean, String],
	scoped: Boolean
}, "VDefaultsProvider");
var VDefaultsProvider = genericComponent(false)({
	name: "VDefaultsProvider",
	props: makeVDefaultsProviderProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { defaults, disabled, reset, root, scoped } = toRefs(props);
		provideDefaults(defaults, {
			reset,
			root,
			scoped,
			disabled
		});
		return () => slots.default?.();
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/dimensions.js
var makeDimensionProps = propsFactory({
	height: [Number, String],
	maxHeight: [Number, String],
	maxWidth: [Number, String],
	minHeight: [Number, String],
	minWidth: [Number, String],
	width: [Number, String]
}, "dimension");
function useDimension(props) {
	return { dimensionStyles: computed(() => {
		const styles = {};
		const height = convertToUnit(props.height);
		const maxHeight = convertToUnit(props.maxHeight);
		const maxWidth = convertToUnit(props.maxWidth);
		const minHeight = convertToUnit(props.minHeight);
		const minWidth = convertToUnit(props.minWidth);
		const width = convertToUnit(props.width);
		if (height != null) styles.height = height;
		if (maxHeight != null) styles.maxHeight = maxHeight;
		if (maxWidth != null) styles.maxWidth = maxWidth;
		if (minHeight != null) styles.minHeight = minHeight;
		if (minWidth != null) styles.minWidth = minWidth;
		if (width != null) styles.width = width;
		return styles;
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/components/VResponsive/VResponsive.js
function useAspectStyles(props) {
	return { aspectStyles: computed(() => {
		const ratio = Number(props.aspectRatio);
		return ratio ? { paddingBottom: String(1 / ratio * 100) + "%" } : void 0;
	}) };
}
var makeVResponsiveProps = propsFactory({
	aspectRatio: [String, Number],
	contentClass: null,
	inline: Boolean,
	...makeComponentProps(),
	...makeDimensionProps()
}, "VResponsive");
var VResponsive = genericComponent()({
	name: "VResponsive",
	props: makeVResponsiveProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { aspectStyles } = useAspectStyles(props);
		const { dimensionStyles } = useDimension(props);
		useRender(() => createVNode("div", {
			"class": [
				"v-responsive",
				{ "v-responsive--inline": props.inline },
				props.class
			],
			"style": [dimensionStyles.value, props.style]
		}, [
			createVNode("div", {
				"class": "v-responsive__sizer",
				"style": aspectStyles.value
			}, null),
			slots.additional?.(),
			slots.default && createVNode("div", { "class": ["v-responsive__content", props.contentClass] }, [slots.default()])
		]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/color.js
function useColor(colors) {
	return destructComputed(() => {
		const _colors = toValue(colors);
		const classes = [];
		const styles = {};
		if (_colors.background) if (isCssColor(_colors.background)) {
			styles.backgroundColor = _colors.background;
			if (!_colors.text && isParsableColor(_colors.background)) {
				const backgroundColor = parseColor(_colors.background);
				if (backgroundColor.a == null || backgroundColor.a === 1) {
					const textColor = getForeground(backgroundColor);
					styles.color = textColor;
					styles.caretColor = textColor;
				}
			}
		} else classes.push(`bg-${_colors.background}`);
		if (_colors.text) if (isCssColor(_colors.text)) {
			styles.color = _colors.text;
			styles.caretColor = _colors.text;
		} else classes.push(`text-${_colors.text}`);
		return {
			colorClasses: classes,
			colorStyles: styles
		};
	});
}
function useTextColor(color) {
	const { colorClasses: textColorClasses, colorStyles: textColorStyles } = useColor(() => ({ text: toValue(color) }));
	return {
		textColorClasses,
		textColorStyles
	};
}
function useBackgroundColor(color) {
	const { colorClasses: backgroundColorClasses, colorStyles: backgroundColorStyles } = useColor(() => ({ background: toValue(color) }));
	return {
		backgroundColorClasses,
		backgroundColorStyles
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/rounded.js
var makeRoundedProps = propsFactory({
	rounded: {
		type: [
			Boolean,
			Number,
			String
		],
		default: void 0
	},
	tile: Boolean
}, "rounded");
function useRounded(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	return { roundedClasses: computed(() => {
		const rounded = isRef(props) ? props.value : props.rounded;
		const tile = isRef(props) ? props.value : props.tile;
		const classes = [];
		if (rounded === true || rounded === "") classes.push(`${name}--rounded`);
		else if (typeof rounded === "string" || rounded === 0) for (const value of String(rounded).split(" ")) classes.push(`rounded-${value}`);
		else if (tile || rounded === false) classes.push("rounded-0");
		return classes;
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/composables/transition.js
var makeTransitionProps = propsFactory({ transition: {
	type: null,
	default: "fade-transition",
	validator: (val) => val !== true
} }, "transition");
var MaybeTransition = (props, _ref) => {
	let { slots } = _ref;
	const { transition, disabled, group, ...rest } = props;
	const { component = group ? TransitionGroup : Transition, ...customProps } = isObject(transition) ? transition : {};
	let transitionProps;
	if (isObject(transition)) transitionProps = mergeProps(customProps, JSON.parse(JSON.stringify({
		disabled,
		group
	})), rest);
	else transitionProps = mergeProps({ name: disabled || !transition ? "" : transition }, rest);
	return h(component, transitionProps, slots);
};
//#endregion
//#region node_modules/vuetify/lib/directives/intersect/index.js
function mounted$5(el, binding) {
	if (!SUPPORTS_INTERSECTION) return;
	const modifiers = binding.modifiers || {};
	const value = binding.value;
	const { handler, options } = typeof value === "object" ? value : {
		handler: value,
		options: {}
	};
	const observer = new IntersectionObserver(function() {
		let entries = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : [];
		let observer = arguments.length > 1 ? arguments[1] : void 0;
		const _observe = el._observe?.[binding.instance.$.uid];
		if (!_observe) return;
		const isIntersecting = entries.some((entry) => entry.isIntersecting);
		if (handler && (!modifiers.quiet || _observe.init) && (!modifiers.once || isIntersecting || _observe.init)) handler(isIntersecting, entries, observer);
		if (isIntersecting && modifiers.once) unmounted$5(el, binding);
		else _observe.init = true;
	}, options);
	el._observe = Object(el._observe);
	el._observe[binding.instance.$.uid] = {
		init: false,
		observer
	};
	observer.observe(el);
}
function unmounted$5(el, binding) {
	const observe = el._observe?.[binding.instance.$.uid];
	if (!observe) return;
	observe.observer.unobserve(el);
	delete el._observe[binding.instance.$.uid];
}
var Intersect = {
	mounted: mounted$5,
	unmounted: unmounted$5
};
//#endregion
//#region node_modules/vuetify/lib/components/VImg/VImg.js
var makeVImgProps = propsFactory({
	absolute: Boolean,
	alt: String,
	cover: Boolean,
	color: String,
	draggable: {
		type: [Boolean, String],
		default: void 0
	},
	eager: Boolean,
	gradient: String,
	lazySrc: String,
	options: {
		type: Object,
		default: () => ({
			root: void 0,
			rootMargin: void 0,
			threshold: void 0
		})
	},
	sizes: String,
	src: {
		type: [String, Object],
		default: ""
	},
	crossorigin: String,
	referrerpolicy: String,
	srcset: String,
	position: String,
	...makeVResponsiveProps(),
	...makeComponentProps(),
	...makeRoundedProps(),
	...makeTransitionProps()
}, "VImg");
var VImg = genericComponent()({
	name: "VImg",
	directives: { intersect: Intersect },
	props: makeVImgProps(),
	emits: {
		loadstart: (value) => true,
		load: (value) => true,
		error: (value) => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { roundedClasses } = useRounded(props);
		const vm = getCurrentInstance$1("VImg");
		const currentSrc = shallowRef("");
		const image = ref();
		const state = shallowRef(props.eager ? "loading" : "idle");
		const naturalWidth = shallowRef();
		const naturalHeight = shallowRef();
		const normalisedSrc = computed(() => {
			return props.src && typeof props.src === "object" ? {
				src: props.src.src,
				srcset: props.srcset || props.src.srcset,
				lazySrc: props.lazySrc || props.src.lazySrc,
				aspect: Number(props.aspectRatio || props.src.aspect || 0)
			} : {
				src: props.src,
				srcset: props.srcset,
				lazySrc: props.lazySrc,
				aspect: Number(props.aspectRatio || 0)
			};
		});
		const aspectRatio = computed(() => {
			return normalisedSrc.value.aspect || naturalWidth.value / naturalHeight.value || 0;
		});
		watch(() => props.src, () => {
			init(state.value !== "idle");
		});
		watch(aspectRatio, (val, oldVal) => {
			if (!val && oldVal && image.value) pollForSize(image.value);
		});
		onBeforeMount(() => init());
		function init(isIntersecting) {
			if (props.eager && isIntersecting) return;
			if (SUPPORTS_INTERSECTION && !isIntersecting && !props.eager) return;
			state.value = "loading";
			if (normalisedSrc.value.lazySrc) {
				const lazyImg = new Image();
				lazyImg.src = normalisedSrc.value.lazySrc;
				pollForSize(lazyImg, null);
			}
			if (!normalisedSrc.value.src) return;
			nextTick(() => {
				emit("loadstart", image.value?.currentSrc || normalisedSrc.value.src);
				setTimeout(() => {
					if (vm.isUnmounted) return;
					if (image.value?.complete) {
						if (!image.value.naturalWidth) onError();
						if (state.value === "error") return;
						if (!aspectRatio.value) pollForSize(image.value, null);
						if (state.value === "loading") onLoad();
					} else {
						if (!aspectRatio.value) pollForSize(image.value);
						getSrc();
					}
				});
			});
		}
		function onLoad() {
			if (vm.isUnmounted) return;
			getSrc();
			pollForSize(image.value);
			state.value = "loaded";
			emit("load", image.value?.currentSrc || normalisedSrc.value.src);
		}
		function onError() {
			if (vm.isUnmounted) return;
			state.value = "error";
			emit("error", image.value?.currentSrc || normalisedSrc.value.src);
		}
		function getSrc() {
			const img = image.value;
			if (img) currentSrc.value = img.currentSrc || img.src;
		}
		let timer = -1;
		onBeforeUnmount(() => {
			clearTimeout(timer);
		});
		function pollForSize(img) {
			let timeout = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : 100;
			const poll = () => {
				clearTimeout(timer);
				if (vm.isUnmounted) return;
				const { naturalHeight: imgHeight, naturalWidth: imgWidth } = img;
				if (imgHeight || imgWidth) {
					naturalWidth.value = imgWidth;
					naturalHeight.value = imgHeight;
				} else if (!img.complete && state.value === "loading" && timeout != null) timer = window.setTimeout(poll, timeout);
				else if (img.currentSrc.endsWith(".svg") || img.currentSrc.startsWith("data:image/svg+xml")) {
					naturalWidth.value = 1;
					naturalHeight.value = 1;
				}
			};
			poll();
		}
		const containClasses = toRef(() => ({
			"v-img__img--cover": props.cover,
			"v-img__img--contain": !props.cover
		}));
		const __image = () => {
			if (!normalisedSrc.value.src || state.value === "idle") return null;
			const img = createVNode("img", {
				"class": ["v-img__img", containClasses.value],
				"style": { objectPosition: props.position },
				"crossorigin": props.crossorigin,
				"src": normalisedSrc.value.src,
				"srcset": normalisedSrc.value.srcset,
				"alt": props.alt,
				"referrerpolicy": props.referrerpolicy,
				"draggable": props.draggable,
				"sizes": props.sizes,
				"ref": image,
				"onLoad": onLoad,
				"onError": onError
			}, null);
			const sources = slots.sources?.();
			return createVNode(MaybeTransition, {
				"transition": props.transition,
				"appear": true
			}, { default: () => [withDirectives(sources ? createVNode("picture", { "class": "v-img__picture" }, [sources, img]) : img, [[vShow, state.value === "loaded"]])] });
		};
		const __preloadImage = () => createVNode(MaybeTransition, { "transition": props.transition }, { default: () => [normalisedSrc.value.lazySrc && state.value !== "loaded" && createVNode("img", {
			"class": [
				"v-img__img",
				"v-img__img--preload",
				containClasses.value
			],
			"style": { objectPosition: props.position },
			"crossorigin": props.crossorigin,
			"src": normalisedSrc.value.lazySrc,
			"alt": props.alt,
			"referrerpolicy": props.referrerpolicy,
			"draggable": props.draggable
		}, null)] });
		const __placeholder = () => {
			if (!slots.placeholder) return null;
			return createVNode(MaybeTransition, {
				"transition": props.transition,
				"appear": true
			}, { default: () => [(state.value === "loading" || state.value === "error" && !slots.error) && createVNode("div", { "class": "v-img__placeholder" }, [slots.placeholder()])] });
		};
		const __error = () => {
			if (!slots.error) return null;
			return createVNode(MaybeTransition, {
				"transition": props.transition,
				"appear": true
			}, { default: () => [state.value === "error" && createVNode("div", { "class": "v-img__error" }, [slots.error()])] });
		};
		const __gradient = () => {
			if (!props.gradient) return null;
			return createVNode("div", {
				"class": "v-img__gradient",
				"style": { backgroundImage: `linear-gradient(${props.gradient})` }
			}, null);
		};
		const isBooted = shallowRef(false);
		{
			const stop = watch(aspectRatio, (val) => {
				if (val) {
					requestAnimationFrame(() => {
						requestAnimationFrame(() => {
							isBooted.value = true;
						});
					});
					stop();
				}
			});
		}
		useRender(() => {
			const responsiveProps = VResponsive.filterProps(props);
			return withDirectives(createVNode(VResponsive, mergeProps({
				"class": [
					"v-img",
					{
						"v-img--absolute": props.absolute,
						"v-img--booting": !isBooted.value
					},
					backgroundColorClasses.value,
					roundedClasses.value,
					props.class
				],
				"style": [
					{ width: convertToUnit(props.width === "auto" ? naturalWidth.value : props.width) },
					backgroundColorStyles.value,
					props.style
				]
			}, responsiveProps, {
				"aspectRatio": aspectRatio.value,
				"aria-label": props.alt,
				"role": props.alt ? "img" : void 0
			}), {
				additional: () => createVNode(Fragment, null, [
					createVNode(__image, null, null),
					createVNode(__preloadImage, null, null),
					createVNode(__gradient, null, null),
					createVNode(__placeholder, null, null),
					createVNode(__error, null, null)
				]),
				default: slots.default
			}), [[
				resolveDirective("intersect"),
				{
					handler: init,
					options: props.options
				},
				null,
				{ once: true }
			]]);
		});
		return {
			currentSrc,
			image,
			state,
			naturalWidth,
			naturalHeight
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/border.js
var makeBorderProps = propsFactory({ border: [
	Boolean,
	Number,
	String
] }, "border");
function useBorder(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	return { borderClasses: computed(() => {
		const border = props.border;
		if (border === true || border === "") return `${name}--border`;
		else if (typeof border === "string" || border === 0) return String(border).split(" ").map((v) => `border-${v}`);
		return [];
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/composables/elevation.js
var makeElevationProps = propsFactory({ elevation: {
	type: [Number, String],
	validator(v) {
		const value = parseInt(v);
		return !isNaN(value) && value >= 0 && value <= 24;
	}
} }, "elevation");
function useElevation(props) {
	return { elevationClasses: toRef(() => {
		const elevation = isRef(props) ? props.value : props.elevation;
		if (elevation == null) return [];
		return [`elevation-${elevation}`];
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/components/VToolbar/VToolbar.js
var allowedDensities$1 = [
	null,
	"prominent",
	"default",
	"comfortable",
	"compact"
];
var makeVToolbarProps = propsFactory({
	absolute: Boolean,
	collapse: Boolean,
	color: String,
	density: {
		type: String,
		default: "default",
		validator: (v) => allowedDensities$1.includes(v)
	},
	extended: Boolean,
	extensionHeight: {
		type: [Number, String],
		default: 48
	},
	flat: Boolean,
	floating: Boolean,
	height: {
		type: [Number, String],
		default: 64
	},
	image: String,
	title: String,
	...makeBorderProps(),
	...makeComponentProps(),
	...makeElevationProps(),
	...makeRoundedProps(),
	...makeTagProps({ tag: "header" }),
	...makeThemeProps()
}, "VToolbar");
var VToolbar = genericComponent()({
	name: "VToolbar",
	props: makeVToolbarProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { borderClasses } = useBorder(props);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		const { themeClasses } = provideTheme(props);
		const { rtlClasses } = useRtl();
		const isExtended = shallowRef(!!(props.extended || slots.extension?.()));
		const contentHeight = computed(() => parseInt(Number(props.height) + (props.density === "prominent" ? Number(props.height) : 0) - (props.density === "comfortable" ? 8 : 0) - (props.density === "compact" ? 16 : 0), 10));
		const extensionHeight = computed(() => isExtended.value ? parseInt(Number(props.extensionHeight) + (props.density === "prominent" ? Number(props.extensionHeight) : 0) - (props.density === "comfortable" ? 4 : 0) - (props.density === "compact" ? 8 : 0), 10) : 0);
		provideDefaults({ VBtn: { variant: "text" } });
		useRender(() => {
			const hasTitle = !!(props.title || slots.title);
			const hasImage = !!(slots.image || props.image);
			const extension = slots.extension?.();
			isExtended.value = !!(props.extended || extension);
			return createVNode(props.tag, {
				"class": [
					"v-toolbar",
					{
						"v-toolbar--absolute": props.absolute,
						"v-toolbar--collapse": props.collapse,
						"v-toolbar--flat": props.flat,
						"v-toolbar--floating": props.floating,
						[`v-toolbar--density-${props.density}`]: true
					},
					backgroundColorClasses.value,
					borderClasses.value,
					elevationClasses.value,
					roundedClasses.value,
					themeClasses.value,
					rtlClasses.value,
					props.class
				],
				"style": [backgroundColorStyles.value, props.style]
			}, { default: () => [
				hasImage && createVNode("div", {
					"key": "image",
					"class": "v-toolbar__image"
				}, [!slots.image ? createVNode(VImg, {
					"key": "image-img",
					"cover": true,
					"src": props.image
				}, null) : createVNode(VDefaultsProvider, {
					"key": "image-defaults",
					"disabled": !props.image,
					"defaults": { VImg: {
						cover: true,
						src: props.image
					} }
				}, slots.image)]),
				createVNode(VDefaultsProvider, { "defaults": { VTabs: { height: convertToUnit(contentHeight.value) } } }, { default: () => [createVNode("div", {
					"class": "v-toolbar__content",
					"style": { height: convertToUnit(contentHeight.value) }
				}, [
					slots.prepend && createVNode("div", { "class": "v-toolbar__prepend" }, [slots.prepend?.()]),
					hasTitle && createVNode(VToolbarTitle, {
						"key": "title",
						"text": props.title
					}, { text: slots.title }),
					slots.default?.(),
					slots.append && createVNode("div", { "class": "v-toolbar__append" }, [slots.append?.()])
				])] }),
				createVNode(VDefaultsProvider, { "defaults": { VTabs: { height: convertToUnit(extensionHeight.value) } } }, { default: () => [createVNode(VExpandTransition, null, { default: () => [isExtended.value && createVNode("div", {
					"class": "v-toolbar__extension",
					"style": { height: convertToUnit(extensionHeight.value) }
				}, [extension])] })] })
			] });
		});
		return {
			contentHeight,
			extensionHeight
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/scroll.js
var makeScrollProps = propsFactory({
	scrollTarget: { type: String },
	scrollThreshold: {
		type: [String, Number],
		default: 300
	}
}, "scroll");
function useScroll(props) {
	const { canScroll } = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : {};
	let previousScroll = 0;
	let previousScrollHeight = 0;
	const target = ref(null);
	const currentScroll = shallowRef(0);
	const savedScroll = shallowRef(0);
	const currentThreshold = shallowRef(0);
	const isScrollActive = shallowRef(false);
	const isScrollingUp = shallowRef(false);
	const scrollThreshold = computed(() => {
		return Number(props.scrollThreshold);
	});
	/**
	* 1: at top
	* 0: at threshold
	*/
	const scrollRatio = computed(() => {
		return clamp((scrollThreshold.value - currentScroll.value) / scrollThreshold.value || 0);
	});
	const onScroll = () => {
		const targetEl = target.value;
		if (!targetEl || canScroll && !canScroll.value) return;
		previousScroll = currentScroll.value;
		currentScroll.value = "window" in targetEl ? targetEl.pageYOffset : targetEl.scrollTop;
		const currentScrollHeight = targetEl instanceof Window ? document.documentElement.scrollHeight : targetEl.scrollHeight;
		if (previousScrollHeight !== currentScrollHeight) {
			previousScrollHeight = currentScrollHeight;
			return;
		}
		isScrollingUp.value = currentScroll.value < previousScroll;
		currentThreshold.value = Math.abs(currentScroll.value - scrollThreshold.value);
	};
	watch(isScrollingUp, () => {
		savedScroll.value = savedScroll.value || currentScroll.value;
	});
	watch(isScrollActive, () => {
		savedScroll.value = 0;
	});
	onMounted(() => {
		watch(() => props.scrollTarget, (scrollTarget) => {
			const newTarget = scrollTarget ? document.querySelector(scrollTarget) : window;
			if (!newTarget) {
				consoleWarn(`Unable to locate element with identifier ${scrollTarget}`);
				return;
			}
			if (newTarget === target.value) return;
			target.value?.removeEventListener("scroll", onScroll);
			target.value = newTarget;
			target.value.addEventListener("scroll", onScroll, { passive: true });
		}, { immediate: true });
	});
	onBeforeUnmount(() => {
		target.value?.removeEventListener("scroll", onScroll);
	});
	canScroll && watch(canScroll, onScroll, { immediate: true });
	return {
		scrollThreshold,
		currentScroll,
		currentThreshold,
		isScrollActive,
		scrollRatio,
		isScrollingUp,
		savedScroll
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/ssrBoot.js
function useSsrBoot() {
	const isBooted = shallowRef(false);
	onMounted(() => {
		window.requestAnimationFrame(() => {
			isBooted.value = true;
		});
	});
	return {
		ssrBootStyles: toRef(() => !isBooted.value ? { transition: "none !important" } : void 0),
		isBooted: readonly(isBooted)
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VAppBar/VAppBar.js
var makeVAppBarProps = propsFactory({
	scrollBehavior: String,
	modelValue: {
		type: Boolean,
		default: true
	},
	location: {
		type: String,
		default: "top",
		validator: (value) => ["top", "bottom"].includes(value)
	},
	...makeVToolbarProps(),
	...makeLayoutItemProps(),
	...makeScrollProps(),
	height: {
		type: [Number, String],
		default: 64
	}
}, "VAppBar");
var VAppBar = genericComponent()({
	name: "VAppBar",
	props: makeVAppBarProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const vToolbarRef = ref();
		const isActive = useProxiedModel(props, "modelValue");
		const scrollBehavior = computed(() => {
			const behavior = new Set(props.scrollBehavior?.split(" ") ?? []);
			return {
				hide: behavior.has("hide"),
				fullyHide: behavior.has("fully-hide"),
				inverted: behavior.has("inverted"),
				collapse: behavior.has("collapse"),
				elevate: behavior.has("elevate"),
				fadeImage: behavior.has("fade-image")
			};
		});
		const { currentScroll, scrollThreshold, isScrollingUp, scrollRatio } = useScroll(props, { canScroll: computed(() => {
			const behavior = scrollBehavior.value;
			return behavior.hide || behavior.fullyHide || behavior.inverted || behavior.collapse || behavior.elevate || behavior.fadeImage || !isActive.value;
		}) });
		const canHide = toRef(() => scrollBehavior.value.hide || scrollBehavior.value.fullyHide);
		const isCollapsed = computed(() => props.collapse || scrollBehavior.value.collapse && (scrollBehavior.value.inverted ? scrollRatio.value > 0 : scrollRatio.value === 0));
		const isFlat = computed(() => props.flat || scrollBehavior.value.fullyHide && !isActive.value || scrollBehavior.value.elevate && (scrollBehavior.value.inverted ? currentScroll.value > 0 : currentScroll.value === 0));
		const opacity = computed(() => scrollBehavior.value.fadeImage ? scrollBehavior.value.inverted ? 1 - scrollRatio.value : scrollRatio.value : void 0);
		const height = computed(() => {
			if (scrollBehavior.value.hide && scrollBehavior.value.inverted) return 0;
			const height = vToolbarRef.value?.contentHeight ?? 0;
			const extensionHeight = vToolbarRef.value?.extensionHeight ?? 0;
			if (!canHide.value) return height + extensionHeight;
			return currentScroll.value < scrollThreshold.value || scrollBehavior.value.fullyHide ? height + extensionHeight : height;
		});
		useToggleScope(() => !!props.scrollBehavior, () => {
			watchEffect(() => {
				if (canHide.value) if (scrollBehavior.value.inverted) isActive.value = currentScroll.value > scrollThreshold.value;
				else isActive.value = isScrollingUp.value || currentScroll.value < scrollThreshold.value;
				else isActive.value = true;
			});
		});
		const { ssrBootStyles } = useSsrBoot();
		const { layoutItemStyles } = useLayoutItem({
			id: props.name,
			order: computed(() => parseInt(props.order, 10)),
			position: toRef(() => props.location),
			layoutSize: height,
			elementSize: shallowRef(void 0),
			active: isActive,
			absolute: toRef(() => props.absolute)
		});
		useRender(() => {
			const toolbarProps = VToolbar.filterProps(props);
			return createVNode(VToolbar, mergeProps({
				"ref": vToolbarRef,
				"class": [
					"v-app-bar",
					{ "v-app-bar--bottom": props.location === "bottom" },
					props.class
				],
				"style": [{
					...layoutItemStyles.value,
					"--v-toolbar-image-opacity": opacity.value,
					height: void 0,
					...ssrBootStyles.value
				}, props.style]
			}, toolbarProps, {
				"collapse": isCollapsed.value,
				"flat": isFlat.value
			}), slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/density.js
var allowedDensities = [
	null,
	"default",
	"comfortable",
	"compact"
];
var makeDensityProps = propsFactory({ density: {
	type: String,
	default: "default",
	validator: (v) => allowedDensities.includes(v)
} }, "density");
function useDensity(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	return { densityClasses: toRef(() => {
		return `${name}--density-${props.density}`;
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/composables/variant.js
var allowedVariants$2 = [
	"elevated",
	"flat",
	"tonal",
	"outlined",
	"text",
	"plain"
];
function genOverlays(isClickable, name) {
	return createVNode(Fragment, null, [isClickable && createVNode("span", {
		"key": "overlay",
		"class": `${name}__overlay`
	}, null), createVNode("span", {
		"key": "underlay",
		"class": `${name}__underlay`
	}, null)]);
}
var makeVariantProps = propsFactory({
	color: String,
	variant: {
		type: String,
		default: "elevated",
		validator: (v) => allowedVariants$2.includes(v)
	}
}, "variant");
function useVariant(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	const variantClasses = toRef(() => {
		const { variant } = toValue(props);
		return `${name}--variant-${variant}`;
	});
	const { colorClasses, colorStyles } = useColor(() => {
		const { variant, color } = toValue(props);
		return { [["elevated", "flat"].includes(variant) ? "background" : "text"]: color };
	});
	return {
		colorClasses,
		colorStyles,
		variantClasses
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VBtnGroup/VBtnGroup.js
var makeVBtnGroupProps = propsFactory({
	baseColor: String,
	divided: Boolean,
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeElevationProps(),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps(),
	...makeVariantProps()
}, "VBtnGroup");
var VBtnGroup = genericComponent()({
	name: "VBtnGroup",
	props: makeVBtnGroupProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { densityClasses } = useDensity(props);
		const { borderClasses } = useBorder(props);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		provideDefaults({ VBtn: {
			height: "auto",
			baseColor: toRef(() => props.baseColor),
			color: toRef(() => props.color),
			density: toRef(() => props.density),
			flat: true,
			variant: toRef(() => props.variant)
		} });
		useRender(() => {
			return createVNode(props.tag, {
				"class": [
					"v-btn-group",
					{ "v-btn-group--divided": props.divided },
					themeClasses.value,
					borderClasses.value,
					densityClasses.value,
					elevationClasses.value,
					roundedClasses.value,
					props.class
				],
				"style": props.style
			}, slots);
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/group.js
var makeGroupProps = propsFactory({
	modelValue: {
		type: null,
		default: void 0
	},
	multiple: Boolean,
	mandatory: [Boolean, String],
	max: Number,
	selectedClass: String,
	disabled: Boolean
}, "group");
var makeGroupItemProps = propsFactory({
	value: null,
	disabled: Boolean,
	selectedClass: String
}, "group-item");
function useGroupItem(props, injectKey) {
	let required = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : true;
	const vm = getCurrentInstance$1("useGroupItem");
	if (!vm) throw new Error("[Vuetify] useGroupItem composable must be used inside a component setup function");
	const id = useId();
	provide(Symbol.for(`${injectKey.description}:id`), id);
	const group = inject(injectKey, null);
	if (!group) {
		if (!required) return group;
		throw new Error(`[Vuetify] Could not find useGroup injection with symbol ${injectKey.description}`);
	}
	const value = toRef(() => props.value);
	const disabled = computed(() => !!(group.disabled.value || props.disabled));
	group.register({
		id,
		value,
		disabled
	}, vm);
	onBeforeUnmount(() => {
		group.unregister(id);
	});
	const isSelected = computed(() => {
		return group.isSelected(id);
	});
	const isFirst = computed(() => {
		return group.items.value[0].id === id;
	});
	const isLast = computed(() => {
		return group.items.value[group.items.value.length - 1].id === id;
	});
	const selectedClass = computed(() => isSelected.value && [group.selectedClass.value, props.selectedClass]);
	watch(isSelected, (value) => {
		vm.emit("group:selected", { value });
	}, { flush: "sync" });
	return {
		id,
		isSelected,
		isFirst,
		isLast,
		toggle: () => group.select(id, !isSelected.value),
		select: (value) => group.select(id, value),
		selectedClass,
		value,
		disabled,
		group
	};
}
function useGroup(props, injectKey) {
	let isUnmounted = false;
	const items = reactive([]);
	const selected = useProxiedModel(props, "modelValue", [], (v) => {
		if (v == null) return [];
		return getIds(items, wrapInArray(v));
	}, (v) => {
		const arr = getValues(items, v);
		return props.multiple ? arr : arr[0];
	});
	const groupVm = getCurrentInstance$1("useGroup");
	function register(item, vm) {
		const unwrapped = item;
		const index = findChildrenWithProvide(Symbol.for(`${injectKey.description}:id`), groupVm?.vnode).indexOf(vm);
		if (unref(unwrapped.value) == null) {
			unwrapped.value = index;
			unwrapped.useIndexAsValue = true;
		}
		if (index > -1) items.splice(index, 0, unwrapped);
		else items.push(unwrapped);
	}
	function unregister(id) {
		if (isUnmounted) return;
		forceMandatoryValue();
		const index = items.findIndex((item) => item.id === id);
		items.splice(index, 1);
	}
	function forceMandatoryValue() {
		const item = items.find((item) => !item.disabled);
		if (item && props.mandatory === "force" && !selected.value.length) selected.value = [item.id];
	}
	onMounted(() => {
		forceMandatoryValue();
	});
	onBeforeUnmount(() => {
		isUnmounted = true;
	});
	onUpdated(() => {
		for (let i = 0; i < items.length; i++) if (items[i].useIndexAsValue) items[i].value = i;
	});
	function select(id, value) {
		const item = items.find((item) => item.id === id);
		if (value && item?.disabled) return;
		if (props.multiple) {
			const internalValue = selected.value.slice();
			const index = internalValue.findIndex((v) => v === id);
			const isSelected = ~index;
			value = value ?? !isSelected;
			if (isSelected && props.mandatory && internalValue.length <= 1) return;
			if (!isSelected && props.max != null && internalValue.length + 1 > props.max) return;
			if (index < 0 && value) internalValue.push(id);
			else if (index >= 0 && !value) internalValue.splice(index, 1);
			selected.value = internalValue;
		} else {
			const isSelected = selected.value.includes(id);
			if (props.mandatory && isSelected) return;
			selected.value = value ?? !isSelected ? [id] : [];
		}
	}
	function step(offset) {
		if (props.multiple) consoleWarn("This method is not supported when using \"multiple\" prop");
		if (!selected.value.length) {
			const item = items.find((item) => !item.disabled);
			item && (selected.value = [item.id]);
		} else {
			const currentId = selected.value[0];
			const currentIndex = items.findIndex((i) => i.id === currentId);
			let newIndex = (currentIndex + offset) % items.length;
			let newItem = items[newIndex];
			while (newItem.disabled && newIndex !== currentIndex) {
				newIndex = (newIndex + offset) % items.length;
				newItem = items[newIndex];
			}
			if (newItem.disabled) return;
			selected.value = [items[newIndex].id];
		}
	}
	const state = {
		register,
		unregister,
		selected,
		select,
		disabled: toRef(() => props.disabled),
		prev: () => step(items.length - 1),
		next: () => step(1),
		isSelected: (id) => selected.value.includes(id),
		selectedClass: toRef(() => props.selectedClass),
		items: toRef(() => items),
		getItemIndex: (value) => getItemIndex(items, value)
	};
	provide(injectKey, state);
	return state;
}
function getItemIndex(items, value) {
	const ids = getIds(items, [value]);
	if (!ids.length) return -1;
	return items.findIndex((item) => item.id === ids[0]);
}
function getIds(items, modelValue) {
	const ids = [];
	modelValue.forEach((value) => {
		const item = items.find((item) => deepEqual(value, item.value));
		const itemByIndex = items[value];
		if (item?.value != null) ids.push(item.id);
		else if (itemByIndex != null) ids.push(itemByIndex.id);
	});
	return ids;
}
function getValues(items, ids) {
	const values = [];
	ids.forEach((id) => {
		const itemIndex = items.findIndex((item) => item.id === id);
		if (~itemIndex) {
			const item = items[itemIndex];
			values.push(item.value != null ? item.value : itemIndex);
		}
	});
	return values;
}
//#endregion
//#region node_modules/vuetify/lib/components/VBtnToggle/VBtnToggle.js
var VBtnToggleSymbol = Symbol.for("vuetify:v-btn-toggle");
var makeVBtnToggleProps = propsFactory({
	...makeVBtnGroupProps(),
	...makeGroupProps()
}, "VBtnToggle");
var VBtnToggle = genericComponent()({
	name: "VBtnToggle",
	props: makeVBtnToggleProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { isSelected, next, prev, select, selected } = useGroup(props, VBtnToggleSymbol);
		useRender(() => {
			const btnGroupProps = VBtnGroup.filterProps(props);
			return createVNode(VBtnGroup, mergeProps({ "class": ["v-btn-toggle", props.class] }, btnGroupProps, { "style": props.style }), { default: () => [slots.default?.({
				isSelected,
				next,
				prev,
				select,
				selected
			})] });
		});
		return {
			next,
			prev,
			select
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/size.js
var predefinedSizes = [
	"x-small",
	"small",
	"default",
	"large",
	"x-large"
];
var makeSizeProps = propsFactory({ size: {
	type: [String, Number],
	default: "default"
} }, "size");
function useSize(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	return destructComputed(() => {
		const size = props.size;
		let sizeClasses;
		let sizeStyles;
		if (includes(predefinedSizes, size)) sizeClasses = `${name}--size-${size}`;
		else if (size) sizeStyles = {
			width: convertToUnit(size),
			height: convertToUnit(size)
		};
		return {
			sizeClasses,
			sizeStyles
		};
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/VIcon/VIcon.js
var makeVIconProps = propsFactory({
	color: String,
	disabled: Boolean,
	start: Boolean,
	end: Boolean,
	icon: IconValue,
	opacity: [String, Number],
	...makeComponentProps(),
	...makeSizeProps(),
	...makeTagProps({ tag: "i" }),
	...makeThemeProps()
}, "VIcon");
var VIcon = genericComponent()({
	name: "VIcon",
	props: makeVIconProps(),
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const slotIcon = shallowRef();
		const { themeClasses } = useTheme();
		const { iconData } = useIcon(() => slotIcon.value || props.icon);
		const { sizeClasses } = useSize(props);
		const { textColorClasses, textColorStyles } = useTextColor(() => props.color);
		useRender(() => {
			const slotValue = slots.default?.();
			if (slotValue) slotIcon.value = flattenFragments(slotValue).filter((node) => node.type === Text && node.children && typeof node.children === "string")[0]?.children;
			const hasClick = !!(attrs.onClick || attrs.onClickOnce);
			return createVNode(iconData.value.component, {
				"tag": props.tag,
				"icon": iconData.value.icon,
				"class": [
					"v-icon",
					"notranslate",
					themeClasses.value,
					sizeClasses.value,
					textColorClasses.value,
					{
						"v-icon--clickable": hasClick,
						"v-icon--disabled": props.disabled,
						"v-icon--start": props.start,
						"v-icon--end": props.end
					},
					props.class
				],
				"style": [
					{ "--v-icon-opacity": props.opacity },
					!sizeClasses.value ? {
						fontSize: convertToUnit(props.size),
						height: convertToUnit(props.size),
						width: convertToUnit(props.size)
					} : void 0,
					textColorStyles.value,
					props.style
				],
				"role": hasClick ? "button" : void 0,
				"aria-hidden": !hasClick,
				"tabindex": hasClick ? props.disabled ? -1 : 0 : void 0
			}, { default: () => [slotValue] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/intersectionObserver.js
function useIntersectionObserver(callback, options) {
	const intersectionRef = ref();
	const isIntersecting = shallowRef(false);
	if (SUPPORTS_INTERSECTION) {
		const observer = new IntersectionObserver((entries) => {
			callback?.(entries, observer);
			isIntersecting.value = !!entries.find((entry) => entry.isIntersecting);
		}, options);
		onBeforeUnmount(() => {
			observer.disconnect();
		});
		watch(intersectionRef, (newValue, oldValue) => {
			if (oldValue) {
				observer.unobserve(oldValue);
				isIntersecting.value = false;
			}
			if (newValue) observer.observe(newValue);
		}, { flush: "post" });
	}
	return {
		intersectionRef,
		isIntersecting
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VProgressCircular/VProgressCircular.js
var makeVProgressCircularProps = propsFactory({
	bgColor: String,
	color: String,
	indeterminate: [Boolean, String],
	modelValue: {
		type: [Number, String],
		default: 0
	},
	rotate: {
		type: [Number, String],
		default: 0
	},
	width: {
		type: [Number, String],
		default: 4
	},
	...makeComponentProps(),
	...makeSizeProps(),
	...makeTagProps({ tag: "div" }),
	...makeThemeProps()
}, "VProgressCircular");
var VProgressCircular = genericComponent()({
	name: "VProgressCircular",
	props: makeVProgressCircularProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const MAGIC_RADIUS_CONSTANT = 20;
		const CIRCUMFERENCE = 2 * Math.PI * MAGIC_RADIUS_CONSTANT;
		const root = ref();
		const { themeClasses } = provideTheme(props);
		const { sizeClasses, sizeStyles } = useSize(props);
		const { textColorClasses, textColorStyles } = useTextColor(() => props.color);
		const { textColorClasses: underlayColorClasses, textColorStyles: underlayColorStyles } = useTextColor(() => props.bgColor);
		const { intersectionRef, isIntersecting } = useIntersectionObserver();
		const { resizeRef, contentRect } = useResizeObserver();
		const normalizedValue = toRef(() => Math.max(0, Math.min(100, parseFloat(props.modelValue))));
		const width = toRef(() => Number(props.width));
		const size = toRef(() => {
			return sizeStyles.value ? Number(props.size) : contentRect.value ? contentRect.value.width : Math.max(width.value, 32);
		});
		const diameter = toRef(() => MAGIC_RADIUS_CONSTANT / (1 - width.value / size.value) * 2);
		const strokeWidth = toRef(() => width.value / size.value * diameter.value);
		const strokeDashOffset = toRef(() => convertToUnit((100 - normalizedValue.value) / 100 * CIRCUMFERENCE));
		watchEffect(() => {
			intersectionRef.value = root.value;
			resizeRef.value = root.value;
		});
		useRender(() => createVNode(props.tag, {
			"ref": root,
			"class": [
				"v-progress-circular",
				{
					"v-progress-circular--indeterminate": !!props.indeterminate,
					"v-progress-circular--visible": isIntersecting.value,
					"v-progress-circular--disable-shrink": props.indeterminate === "disable-shrink"
				},
				themeClasses.value,
				sizeClasses.value,
				textColorClasses.value,
				props.class
			],
			"style": [
				sizeStyles.value,
				textColorStyles.value,
				props.style
			],
			"role": "progressbar",
			"aria-valuemin": "0",
			"aria-valuemax": "100",
			"aria-valuenow": props.indeterminate ? void 0 : normalizedValue.value
		}, { default: () => [createVNode("svg", {
			"style": { transform: `rotate(calc(-90deg + ${Number(props.rotate)}deg))` },
			"xmlns": "http://www.w3.org/2000/svg",
			"viewBox": `0 0 ${diameter.value} ${diameter.value}`
		}, [createVNode("circle", {
			"class": ["v-progress-circular__underlay", underlayColorClasses.value],
			"style": underlayColorStyles.value,
			"fill": "transparent",
			"cx": "50%",
			"cy": "50%",
			"r": MAGIC_RADIUS_CONSTANT,
			"stroke-width": strokeWidth.value,
			"stroke-dasharray": CIRCUMFERENCE,
			"stroke-dashoffset": 0
		}, null), createVNode("circle", {
			"class": "v-progress-circular__overlay",
			"fill": "transparent",
			"cx": "50%",
			"cy": "50%",
			"r": MAGIC_RADIUS_CONSTANT,
			"stroke-width": strokeWidth.value,
			"stroke-dasharray": CIRCUMFERENCE,
			"stroke-dashoffset": strokeDashOffset.value
		}, null)]), slots.default && createVNode("div", { "class": "v-progress-circular__content" }, [slots.default({ value: normalizedValue.value })])] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/location.js
var oppositeMap = {
	center: "center",
	top: "bottom",
	bottom: "top",
	left: "right",
	right: "left"
};
var makeLocationProps = propsFactory({ location: String }, "location");
function useLocation(props) {
	let opposite = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : false;
	let offset = arguments.length > 2 ? arguments[2] : void 0;
	const { isRtl } = useRtl();
	return { locationStyles: computed(() => {
		if (!props.location) return {};
		const { side, align } = parseAnchor(props.location.split(" ").length > 1 ? props.location : `${props.location} center`, isRtl.value);
		function getOffset(side) {
			return offset ? offset(side) : 0;
		}
		const styles = {};
		if (side !== "center") if (opposite) styles[oppositeMap[side]] = `calc(100% - ${getOffset(side)}px)`;
		else styles[side] = 0;
		if (align !== "center") if (opposite) styles[oppositeMap[align]] = `calc(100% - ${getOffset(align)}px)`;
		else styles[align] = 0;
		else {
			if (side === "center") styles.top = styles.left = "50%";
			else styles[{
				top: "left",
				bottom: "left",
				left: "top",
				right: "top"
			}[side]] = "50%";
			styles.transform = {
				top: "translateX(-50%)",
				bottom: "translateX(-50%)",
				left: "translateY(-50%)",
				right: "translateY(-50%)",
				center: "translate(-50%, -50%)"
			}[side];
		}
		return styles;
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/components/VProgressLinear/VProgressLinear.js
var makeVProgressLinearProps = propsFactory({
	absolute: Boolean,
	active: {
		type: Boolean,
		default: true
	},
	bgColor: String,
	bgOpacity: [Number, String],
	bufferValue: {
		type: [Number, String],
		default: 0
	},
	bufferColor: String,
	bufferOpacity: [Number, String],
	clickable: Boolean,
	color: String,
	height: {
		type: [Number, String],
		default: 4
	},
	indeterminate: Boolean,
	max: {
		type: [Number, String],
		default: 100
	},
	modelValue: {
		type: [Number, String],
		default: 0
	},
	opacity: [Number, String],
	reverse: Boolean,
	stream: Boolean,
	striped: Boolean,
	roundedBar: Boolean,
	...makeComponentProps(),
	...makeLocationProps({ location: "top" }),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VProgressLinear");
var VProgressLinear = genericComponent()({
	name: "VProgressLinear",
	props: makeVProgressLinearProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const progress = useProxiedModel(props, "modelValue");
		const { isRtl, rtlClasses } = useRtl();
		const { themeClasses } = provideTheme(props);
		const { locationStyles } = useLocation(props);
		const { textColorClasses, textColorStyles } = useTextColor(() => props.color);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor || props.color);
		const { backgroundColorClasses: bufferColorClasses, backgroundColorStyles: bufferColorStyles } = useBackgroundColor(() => props.bufferColor || props.bgColor || props.color);
		const { backgroundColorClasses: barColorClasses, backgroundColorStyles: barColorStyles } = useBackgroundColor(() => props.color);
		const { roundedClasses } = useRounded(props);
		const { intersectionRef, isIntersecting } = useIntersectionObserver();
		const max = computed(() => parseFloat(props.max));
		const height = computed(() => parseFloat(props.height));
		const normalizedBuffer = computed(() => clamp(parseFloat(props.bufferValue) / max.value * 100, 0, 100));
		const normalizedValue = computed(() => clamp(parseFloat(progress.value) / max.value * 100, 0, 100));
		const isReversed = computed(() => isRtl.value !== props.reverse);
		const transition = computed(() => props.indeterminate ? "fade-transition" : "slide-x-transition");
		const isForcedColorsModeActive = IN_BROWSER && window.matchMedia?.("(forced-colors: active)").matches;
		function handleClick(e) {
			if (!intersectionRef.value) return;
			const { left, right, width } = intersectionRef.value.getBoundingClientRect();
			const value = isReversed.value ? width - e.clientX + (right - width) : e.clientX - left;
			progress.value = Math.round(value / width * max.value);
		}
		useRender(() => createVNode(props.tag, {
			"ref": intersectionRef,
			"class": [
				"v-progress-linear",
				{
					"v-progress-linear--absolute": props.absolute,
					"v-progress-linear--active": props.active && isIntersecting.value,
					"v-progress-linear--reverse": isReversed.value,
					"v-progress-linear--rounded": props.rounded,
					"v-progress-linear--rounded-bar": props.roundedBar,
					"v-progress-linear--striped": props.striped
				},
				roundedClasses.value,
				themeClasses.value,
				rtlClasses.value,
				props.class
			],
			"style": [{
				bottom: props.location === "bottom" ? 0 : void 0,
				top: props.location === "top" ? 0 : void 0,
				height: props.active ? convertToUnit(height.value) : 0,
				"--v-progress-linear-height": convertToUnit(height.value),
				...props.absolute ? locationStyles.value : {}
			}, props.style],
			"role": "progressbar",
			"aria-hidden": props.active ? "false" : "true",
			"aria-valuemin": "0",
			"aria-valuemax": props.max,
			"aria-valuenow": props.indeterminate ? void 0 : Math.min(parseFloat(progress.value), max.value),
			"onClick": props.clickable && handleClick
		}, { default: () => [
			props.stream && createVNode("div", {
				"key": "stream",
				"class": ["v-progress-linear__stream", textColorClasses.value],
				"style": {
					...textColorStyles.value,
					[isReversed.value ? "left" : "right"]: convertToUnit(-height.value),
					borderTop: `${convertToUnit(height.value / 2)} dotted`,
					opacity: parseFloat(props.bufferOpacity),
					top: `calc(50% - ${convertToUnit(height.value / 4)})`,
					width: convertToUnit(100 - normalizedBuffer.value, "%"),
					"--v-progress-linear-stream-to": convertToUnit(height.value * (isReversed.value ? 1 : -1))
				}
			}, null),
			createVNode("div", {
				"class": ["v-progress-linear__background", !isForcedColorsModeActive ? backgroundColorClasses.value : void 0],
				"style": [backgroundColorStyles.value, {
					opacity: parseFloat(props.bgOpacity),
					width: props.stream ? 0 : void 0
				}]
			}, null),
			createVNode("div", {
				"class": ["v-progress-linear__buffer", !isForcedColorsModeActive ? bufferColorClasses.value : void 0],
				"style": [bufferColorStyles.value, {
					opacity: parseFloat(props.bufferOpacity),
					width: convertToUnit(normalizedBuffer.value, "%")
				}]
			}, null),
			createVNode(Transition, { "name": transition.value }, { default: () => [!props.indeterminate ? createVNode("div", {
				"class": ["v-progress-linear__determinate", !isForcedColorsModeActive ? barColorClasses.value : void 0],
				"style": [barColorStyles.value, { width: convertToUnit(normalizedValue.value, "%") }]
			}, null) : createVNode("div", { "class": "v-progress-linear__indeterminate" }, [["long", "short"].map((bar) => createVNode("div", {
				"key": bar,
				"class": [
					"v-progress-linear__indeterminate",
					bar,
					!isForcedColorsModeActive ? barColorClasses.value : void 0
				],
				"style": barColorStyles.value
			}, null))])] }),
			slots.default && createVNode("div", { "class": "v-progress-linear__content" }, [slots.default({
				value: normalizedValue.value,
				buffer: normalizedBuffer.value
			})])
		] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/loader.js
var makeLoaderProps = propsFactory({ loading: [Boolean, String] }, "loader");
function useLoader(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	return { loaderClasses: toRef(() => ({ [`${name}--loading`]: props.loading })) };
}
function LoaderSlot(props, _ref) {
	let { slots } = _ref;
	return createVNode("div", { "class": `${props.name}__loader` }, [slots.default?.({
		color: props.color,
		isActive: props.active
	}) || createVNode(VProgressLinear, {
		"absolute": props.absolute,
		"active": props.active,
		"color": props.color,
		"height": "2",
		"indeterminate": true
	}, null)]);
}
//#endregion
//#region node_modules/vuetify/lib/composables/position.js
var positionValues = [
	"static",
	"relative",
	"fixed",
	"absolute",
	"sticky"
];
var makePositionProps = propsFactory({ position: {
	type: String,
	validator: (v) => positionValues.includes(v)
} }, "position");
function usePosition(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	return { positionClasses: toRef(() => {
		return props.position ? `${name}--${props.position}` : void 0;
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/composables/router.js
function useRoute() {
	const vm = getCurrentInstance$1("useRoute");
	return computed(() => vm?.proxy?.$route);
}
function useRouter() {
	return getCurrentInstance$1("useRouter")?.proxy?.$router;
}
function useLink(props, attrs) {
	const RouterLink = resolveDynamicComponent("RouterLink");
	const isLink = toRef(() => !!(props.href || props.to));
	const isClickable = computed(() => {
		return isLink?.value || hasEvent(attrs, "click") || hasEvent(props, "click");
	});
	if (typeof RouterLink === "string" || !("useLink" in RouterLink)) {
		const href = toRef(() => props.href);
		return {
			isLink,
			isClickable,
			href,
			linkProps: reactive({ href })
		};
	}
	const routerLink = RouterLink.useLink({
		to: toRef(() => props.to || ""),
		replace: toRef(() => props.replace)
	});
	const link = computed(() => props.to ? routerLink : void 0);
	const route = useRoute();
	const isActive = computed(() => {
		if (!link.value) return false;
		if (!props.exact) return link.value.isActive?.value ?? false;
		if (!route.value) return link.value.isExactActive?.value ?? false;
		return link.value.isExactActive?.value && deepEqual(link.value.route.value.query, route.value.query);
	});
	const href = computed(() => props.to ? link.value?.route.value.href : props.href);
	return {
		isLink,
		isClickable,
		isActive,
		route: link.value?.route,
		navigate: link.value?.navigate,
		href,
		linkProps: reactive({
			href,
			"aria-current": toRef(() => isActive.value ? "page" : void 0)
		})
	};
}
var makeRouterProps = propsFactory({
	href: String,
	replace: Boolean,
	to: [String, Object],
	exact: Boolean
}, "router");
var inTransition = false;
function useBackButton(router, cb) {
	let popped = false;
	let removeBefore;
	let removeAfter;
	if (IN_BROWSER && router?.beforeEach) {
		nextTick(() => {
			window.addEventListener("popstate", onPopstate);
			removeBefore = router.beforeEach((to, from, next) => {
				if (!inTransition) setTimeout(() => popped ? cb(next) : next());
				else popped ? cb(next) : next();
				inTransition = true;
			});
			removeAfter = router?.afterEach(() => {
				inTransition = false;
			});
		});
		onScopeDispose(() => {
			window.removeEventListener("popstate", onPopstate);
			removeBefore?.();
			removeAfter?.();
		});
	}
	function onPopstate(e) {
		if (e.state?.replaced) return;
		popped = true;
		setTimeout(() => popped = false);
	}
}
//#endregion
//#region node_modules/vuetify/lib/composables/selectLink.js
function useSelectLink(link, select) {
	watch(() => link.isActive?.value, (isActive) => {
		if (link.isLink.value && isActive && select) nextTick(() => {
			select(true);
		});
	}, { immediate: true });
}
//#endregion
//#region node_modules/vuetify/lib/directives/ripple/index.js
var stopSymbol = Symbol("rippleStop");
var DELAY_RIPPLE = 80;
function transform(el, value) {
	el.style.transform = value;
	el.style.webkitTransform = value;
}
function isTouchEvent(e) {
	return e.constructor.name === "TouchEvent";
}
function isKeyboardEvent(e) {
	return e.constructor.name === "KeyboardEvent";
}
var calculate = function(e, el) {
	let value = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : {};
	let localX = 0;
	let localY = 0;
	if (!isKeyboardEvent(e)) {
		const offset = el.getBoundingClientRect();
		const target = isTouchEvent(e) ? e.touches[e.touches.length - 1] : e;
		localX = target.clientX - offset.left;
		localY = target.clientY - offset.top;
	}
	let radius = 0;
	let scale = .3;
	if (el._ripple?.circle) {
		scale = .15;
		radius = el.clientWidth / 2;
		radius = value.center ? radius : radius + Math.sqrt((localX - radius) ** 2 + (localY - radius) ** 2) / 4;
	} else radius = Math.sqrt(el.clientWidth ** 2 + el.clientHeight ** 2) / 2;
	const centerX = `${(el.clientWidth - radius * 2) / 2}px`;
	const centerY = `${(el.clientHeight - radius * 2) / 2}px`;
	const x = value.center ? centerX : `${localX - radius}px`;
	const y = value.center ? centerY : `${localY - radius}px`;
	return {
		radius,
		scale,
		x,
		y,
		centerX,
		centerY
	};
};
var ripples = {
	show(e, el) {
		let value = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : {};
		if (!el?._ripple?.enabled) return;
		const container = document.createElement("span");
		const animation = document.createElement("span");
		container.appendChild(animation);
		container.className = "v-ripple__container";
		if (value.class) container.className += ` ${value.class}`;
		const { radius, scale, x, y, centerX, centerY } = calculate(e, el, value);
		const size = `${radius * 2}px`;
		animation.className = "v-ripple__animation";
		animation.style.width = size;
		animation.style.height = size;
		el.appendChild(container);
		const computed = window.getComputedStyle(el);
		if (computed && computed.position === "static") {
			el.style.position = "relative";
			el.dataset.previousPosition = "static";
		}
		animation.classList.add("v-ripple__animation--enter");
		animation.classList.add("v-ripple__animation--visible");
		transform(animation, `translate(${x}, ${y}) scale3d(${scale},${scale},${scale})`);
		animation.dataset.activated = String(performance.now());
		requestAnimationFrame(() => {
			requestAnimationFrame(() => {
				animation.classList.remove("v-ripple__animation--enter");
				animation.classList.add("v-ripple__animation--in");
				transform(animation, `translate(${centerX}, ${centerY}) scale3d(1,1,1)`);
			});
		});
	},
	hide(el) {
		if (!el?._ripple?.enabled) return;
		const ripples = el.getElementsByClassName("v-ripple__animation");
		if (ripples.length === 0) return;
		const animation = ripples[ripples.length - 1];
		if (animation.dataset.isHiding) return;
		else animation.dataset.isHiding = "true";
		const diff = performance.now() - Number(animation.dataset.activated);
		const delay = Math.max(250 - diff, 0);
		setTimeout(() => {
			animation.classList.remove("v-ripple__animation--in");
			animation.classList.add("v-ripple__animation--out");
			setTimeout(() => {
				if (el.getElementsByClassName("v-ripple__animation").length === 1 && el.dataset.previousPosition) {
					el.style.position = el.dataset.previousPosition;
					delete el.dataset.previousPosition;
				}
				if (animation.parentNode?.parentNode === el) el.removeChild(animation.parentNode);
			}, 300);
		}, delay);
	}
};
function isRippleEnabled(value) {
	return typeof value === "undefined" || !!value;
}
function rippleShow(e) {
	const value = {};
	const element = e.currentTarget;
	if (!element?._ripple || element._ripple.touched || e[stopSymbol]) return;
	e[stopSymbol] = true;
	if (isTouchEvent(e)) {
		element._ripple.touched = true;
		element._ripple.isTouch = true;
	} else if (element._ripple.isTouch) return;
	value.center = element._ripple.centered || isKeyboardEvent(e);
	if (element._ripple.class) value.class = element._ripple.class;
	if (isTouchEvent(e)) {
		if (element._ripple.showTimerCommit) return;
		element._ripple.showTimerCommit = () => {
			ripples.show(e, element, value);
		};
		element._ripple.showTimer = window.setTimeout(() => {
			if (element?._ripple?.showTimerCommit) {
				element._ripple.showTimerCommit();
				element._ripple.showTimerCommit = null;
			}
		}, DELAY_RIPPLE);
	} else ripples.show(e, element, value);
}
function rippleStop(e) {
	e[stopSymbol] = true;
}
function rippleHide(e) {
	const element = e.currentTarget;
	if (!element?._ripple) return;
	window.clearTimeout(element._ripple.showTimer);
	if (e.type === "touchend" && element._ripple.showTimerCommit) {
		element._ripple.showTimerCommit();
		element._ripple.showTimerCommit = null;
		element._ripple.showTimer = window.setTimeout(() => {
			rippleHide(e);
		});
		return;
	}
	window.setTimeout(() => {
		if (element._ripple) element._ripple.touched = false;
	});
	ripples.hide(element);
}
function rippleCancelShow(e) {
	const element = e.currentTarget;
	if (!element?._ripple) return;
	if (element._ripple.showTimerCommit) element._ripple.showTimerCommit = null;
	window.clearTimeout(element._ripple.showTimer);
}
var keyboardRipple = false;
function keyboardRippleShow(e) {
	if (!keyboardRipple && (e.keyCode === keyCodes.enter || e.keyCode === keyCodes.space)) {
		keyboardRipple = true;
		rippleShow(e);
	}
}
function keyboardRippleHide(e) {
	keyboardRipple = false;
	rippleHide(e);
}
function focusRippleHide(e) {
	if (keyboardRipple) {
		keyboardRipple = false;
		rippleHide(e);
	}
}
function updateRipple(el, binding, wasEnabled) {
	const { value, modifiers } = binding;
	const enabled = isRippleEnabled(value);
	if (!enabled) ripples.hide(el);
	el._ripple = el._ripple ?? {};
	el._ripple.enabled = enabled;
	el._ripple.centered = modifiers.center;
	el._ripple.circle = modifiers.circle;
	if (isObject(value) && value.class) el._ripple.class = value.class;
	if (enabled && !wasEnabled) {
		if (modifiers.stop) {
			el.addEventListener("touchstart", rippleStop, { passive: true });
			el.addEventListener("mousedown", rippleStop);
			return;
		}
		el.addEventListener("touchstart", rippleShow, { passive: true });
		el.addEventListener("touchend", rippleHide, { passive: true });
		el.addEventListener("touchmove", rippleCancelShow, { passive: true });
		el.addEventListener("touchcancel", rippleHide);
		el.addEventListener("mousedown", rippleShow);
		el.addEventListener("mouseup", rippleHide);
		el.addEventListener("mouseleave", rippleHide);
		el.addEventListener("keydown", keyboardRippleShow);
		el.addEventListener("keyup", keyboardRippleHide);
		el.addEventListener("blur", focusRippleHide);
		el.addEventListener("dragstart", rippleHide, { passive: true });
	} else if (!enabled && wasEnabled) removeListeners(el);
}
function removeListeners(el) {
	el.removeEventListener("mousedown", rippleShow);
	el.removeEventListener("touchstart", rippleShow);
	el.removeEventListener("touchend", rippleHide);
	el.removeEventListener("touchmove", rippleCancelShow);
	el.removeEventListener("touchcancel", rippleHide);
	el.removeEventListener("mouseup", rippleHide);
	el.removeEventListener("mouseleave", rippleHide);
	el.removeEventListener("keydown", keyboardRippleShow);
	el.removeEventListener("keyup", keyboardRippleHide);
	el.removeEventListener("dragstart", rippleHide);
	el.removeEventListener("blur", focusRippleHide);
}
function mounted$4(el, binding) {
	updateRipple(el, binding, false);
}
function unmounted$4(el) {
	delete el._ripple;
	removeListeners(el);
}
function updated$1(el, binding) {
	if (binding.value === binding.oldValue) return;
	updateRipple(el, binding, isRippleEnabled(binding.oldValue));
}
var Ripple = {
	mounted: mounted$4,
	unmounted: unmounted$4,
	updated: updated$1
};
//#endregion
//#region node_modules/vuetify/lib/components/VBtn/VBtn.js
var makeVBtnProps = propsFactory({
	active: {
		type: Boolean,
		default: void 0
	},
	activeColor: String,
	baseColor: String,
	symbol: {
		type: null,
		default: VBtnToggleSymbol
	},
	flat: Boolean,
	icon: [
		Boolean,
		String,
		Function,
		Object
	],
	prependIcon: IconValue,
	appendIcon: IconValue,
	block: Boolean,
	readonly: Boolean,
	slim: Boolean,
	stacked: Boolean,
	ripple: {
		type: [Boolean, Object],
		default: true
	},
	text: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeDimensionProps(),
	...makeElevationProps(),
	...makeGroupItemProps(),
	...makeLoaderProps(),
	...makeLocationProps(),
	...makePositionProps(),
	...makeRoundedProps(),
	...makeRouterProps(),
	...makeSizeProps(),
	...makeTagProps({ tag: "button" }),
	...makeThemeProps(),
	...makeVariantProps({ variant: "elevated" })
}, "VBtn");
var VBtn = genericComponent()({
	name: "VBtn",
	props: makeVBtnProps(),
	emits: { "group:selected": (val) => true },
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { borderClasses } = useBorder(props);
		const { densityClasses } = useDensity(props);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { loaderClasses } = useLoader(props);
		const { locationStyles } = useLocation(props);
		const { positionClasses } = usePosition(props);
		const { roundedClasses } = useRounded(props);
		const { sizeClasses, sizeStyles } = useSize(props);
		const group = useGroupItem(props, props.symbol, false);
		const link = useLink(props, attrs);
		const isActive = computed(() => {
			if (props.active !== void 0) return props.active;
			if (link.isLink.value) return link.isActive?.value;
			return group?.isSelected.value;
		});
		const color = toRef(() => isActive.value ? props.activeColor ?? props.color : props.color);
		const { colorClasses, colorStyles, variantClasses } = useVariant(computed(() => {
			return {
				color: group?.isSelected.value && (!link.isLink.value || link.isActive?.value) || !group || link.isActive?.value ? color.value ?? props.baseColor : props.baseColor,
				variant: props.variant
			};
		}));
		const isDisabled = computed(() => group?.disabled.value || props.disabled);
		const isElevated = toRef(() => {
			return props.variant === "elevated" && !(props.disabled || props.flat || props.border);
		});
		const valueAttr = computed(() => {
			if (props.value === void 0 || typeof props.value === "symbol") return void 0;
			return Object(props.value) === props.value ? JSON.stringify(props.value, null, 0) : props.value;
		});
		function onClick(e) {
			if (isDisabled.value || link.isLink.value && (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0 || attrs.target === "_blank")) return;
			link.navigate?.(e);
			group?.toggle();
		}
		useSelectLink(link, group?.select);
		useRender(() => {
			const Tag = link.isLink.value ? "a" : props.tag;
			const hasPrepend = !!(props.prependIcon || slots.prepend);
			const hasAppend = !!(props.appendIcon || slots.append);
			const hasIcon = !!(props.icon && props.icon !== true);
			return withDirectives(createVNode(Tag, mergeProps({
				"type": Tag === "a" ? void 0 : "button",
				"class": [
					"v-btn",
					group?.selectedClass.value,
					{
						"v-btn--active": isActive.value,
						"v-btn--block": props.block,
						"v-btn--disabled": isDisabled.value,
						"v-btn--elevated": isElevated.value,
						"v-btn--flat": props.flat,
						"v-btn--icon": !!props.icon,
						"v-btn--loading": props.loading,
						"v-btn--readonly": props.readonly,
						"v-btn--slim": props.slim,
						"v-btn--stacked": props.stacked
					},
					themeClasses.value,
					borderClasses.value,
					colorClasses.value,
					densityClasses.value,
					elevationClasses.value,
					loaderClasses.value,
					positionClasses.value,
					roundedClasses.value,
					sizeClasses.value,
					variantClasses.value,
					props.class
				],
				"style": [
					colorStyles.value,
					dimensionStyles.value,
					locationStyles.value,
					sizeStyles.value,
					props.style
				],
				"aria-busy": props.loading ? true : void 0,
				"disabled": isDisabled.value || void 0,
				"tabindex": props.loading || props.readonly ? -1 : void 0,
				"onClick": onClick,
				"value": valueAttr.value
			}, link.linkProps), { default: () => [
				genOverlays(true, "v-btn"),
				!props.icon && hasPrepend && createVNode("span", {
					"key": "prepend",
					"class": "v-btn__prepend"
				}, [!slots.prepend ? createVNode(VIcon, {
					"key": "prepend-icon",
					"icon": props.prependIcon
				}, null) : createVNode(VDefaultsProvider, {
					"key": "prepend-defaults",
					"disabled": !props.prependIcon,
					"defaults": { VIcon: { icon: props.prependIcon } }
				}, slots.prepend)]),
				createVNode("span", {
					"class": "v-btn__content",
					"data-no-activator": ""
				}, [!slots.default && hasIcon ? createVNode(VIcon, {
					"key": "content-icon",
					"icon": props.icon
				}, null) : createVNode(VDefaultsProvider, {
					"key": "content-defaults",
					"disabled": !hasIcon,
					"defaults": { VIcon: { icon: props.icon } }
				}, { default: () => [slots.default?.() ?? toDisplayString(props.text)] })]),
				!props.icon && hasAppend && createVNode("span", {
					"key": "append",
					"class": "v-btn__append"
				}, [!slots.append ? createVNode(VIcon, {
					"key": "append-icon",
					"icon": props.appendIcon
				}, null) : createVNode(VDefaultsProvider, {
					"key": "append-defaults",
					"disabled": !props.appendIcon,
					"defaults": { VIcon: { icon: props.appendIcon } }
				}, slots.append)]),
				!!props.loading && createVNode("span", {
					"key": "loader",
					"class": "v-btn__loader"
				}, [slots.loader?.() ?? createVNode(VProgressCircular, {
					"color": typeof props.loading === "boolean" ? void 0 : props.loading,
					"indeterminate": true,
					"width": "2"
				}, null)])
			] }), [[
				Ripple,
				!isDisabled.value && props.ripple,
				"",
				{ center: !!props.icon }
			]]);
		});
		return { group };
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VAppBar/VAppBarNavIcon.js
var makeVAppBarNavIconProps = propsFactory({ ...makeVBtnProps({
	icon: "$menu",
	variant: "text"
}) }, "VAppBarNavIcon");
var VAppBarNavIcon = genericComponent()({
	name: "VAppBarNavIcon",
	props: makeVAppBarNavIconProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode(VBtn, mergeProps(props, { "class": ["v-app-bar-nav-icon"] }), slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VAppBar/VAppBarTitle.js
var VAppBarTitle = genericComponent()({
	name: "VAppBarTitle",
	props: makeVToolbarTitleProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode(VToolbarTitle, mergeProps(props, { "class": "v-app-bar-title" }), slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VAlert/VAlertTitle.js
var VAlertTitle = createSimpleFunctional("v-alert-title");
//#endregion
//#region node_modules/vuetify/lib/components/VAlert/VAlert.js
var allowedTypes = [
	"success",
	"info",
	"warning",
	"error"
];
var makeVAlertProps = propsFactory({
	border: {
		type: [Boolean, String],
		validator: (val) => {
			return typeof val === "boolean" || [
				"top",
				"end",
				"bottom",
				"start"
			].includes(val);
		}
	},
	borderColor: String,
	closable: Boolean,
	closeIcon: {
		type: IconValue,
		default: "$close"
	},
	closeLabel: {
		type: String,
		default: "$vuetify.close"
	},
	icon: {
		type: [
			Boolean,
			String,
			Function,
			Object
		],
		default: null
	},
	modelValue: {
		type: Boolean,
		default: true
	},
	prominent: Boolean,
	title: String,
	text: String,
	type: {
		type: String,
		validator: (val) => allowedTypes.includes(val)
	},
	...makeComponentProps(),
	...makeDensityProps(),
	...makeDimensionProps(),
	...makeElevationProps(),
	...makeLocationProps(),
	...makePositionProps(),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps(),
	...makeVariantProps({ variant: "flat" })
}, "VAlert");
var VAlert = genericComponent()({
	name: "VAlert",
	props: makeVAlertProps(),
	emits: {
		"click:close": (e) => true,
		"update:modelValue": (value) => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const isActive = useProxiedModel(props, "modelValue");
		const icon = toRef(() => {
			if (props.icon === false) return void 0;
			if (!props.type) return props.icon;
			return props.icon ?? `$${props.type}`;
		});
		const { themeClasses } = provideTheme(props);
		const { colorClasses, colorStyles, variantClasses } = useVariant(() => ({
			color: props.color ?? props.type,
			variant: props.variant
		}));
		const { densityClasses } = useDensity(props);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { locationStyles } = useLocation(props);
		const { positionClasses } = usePosition(props);
		const { roundedClasses } = useRounded(props);
		const { textColorClasses, textColorStyles } = useTextColor(() => props.borderColor);
		const { t } = useLocale();
		const closeProps = toRef(() => ({
			"aria-label": t(props.closeLabel),
			onClick(e) {
				isActive.value = false;
				emit("click:close", e);
			}
		}));
		return () => {
			const hasPrepend = !!(slots.prepend || icon.value);
			const hasTitle = !!(slots.title || props.title);
			const hasClose = !!(slots.close || props.closable);
			return isActive.value && createVNode(props.tag, {
				"class": [
					"v-alert",
					props.border && {
						"v-alert--border": !!props.border,
						[`v-alert--border-${props.border === true ? "start" : props.border}`]: true
					},
					{ "v-alert--prominent": props.prominent },
					themeClasses.value,
					colorClasses.value,
					densityClasses.value,
					elevationClasses.value,
					positionClasses.value,
					roundedClasses.value,
					variantClasses.value,
					props.class
				],
				"style": [
					colorStyles.value,
					dimensionStyles.value,
					locationStyles.value,
					props.style
				],
				"role": "alert"
			}, { default: () => [
				genOverlays(false, "v-alert"),
				props.border && createVNode("div", {
					"key": "border",
					"class": ["v-alert__border", textColorClasses.value],
					"style": textColorStyles.value
				}, null),
				hasPrepend && createVNode("div", {
					"key": "prepend",
					"class": "v-alert__prepend"
				}, [!slots.prepend ? createVNode(VIcon, {
					"key": "prepend-icon",
					"density": props.density,
					"icon": icon.value,
					"size": props.prominent ? 44 : 28
				}, null) : createVNode(VDefaultsProvider, {
					"key": "prepend-defaults",
					"disabled": !icon.value,
					"defaults": { VIcon: {
						density: props.density,
						icon: icon.value,
						size: props.prominent ? 44 : 28
					} }
				}, slots.prepend)]),
				createVNode("div", { "class": "v-alert__content" }, [
					hasTitle && createVNode(VAlertTitle, { "key": "title" }, { default: () => [slots.title?.() ?? props.title] }),
					slots.text?.() ?? props.text,
					slots.default?.()
				]),
				slots.append && createVNode("div", {
					"key": "append",
					"class": "v-alert__append"
				}, [slots.append()]),
				hasClose && createVNode("div", {
					"key": "close",
					"class": "v-alert__close"
				}, [!slots.close ? createVNode(VBtn, mergeProps({
					"key": "close-btn",
					"icon": props.closeIcon,
					"size": "x-small",
					"variant": "text"
				}, closeProps.value), null) : createVNode(VDefaultsProvider, {
					"key": "close-defaults",
					"defaults": { VBtn: {
						icon: props.closeIcon,
						size: "x-small",
						variant: "text"
					} }
				}, { default: () => [slots.close?.({ props: closeProps.value })] })])
			] });
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VAvatar/VAvatar.js
var makeVAvatarProps = propsFactory({
	start: Boolean,
	end: Boolean,
	icon: IconValue,
	image: String,
	text: String,
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeRoundedProps(),
	...makeSizeProps(),
	...makeTagProps(),
	...makeThemeProps(),
	...makeVariantProps({ variant: "flat" })
}, "VAvatar");
var VAvatar = genericComponent()({
	name: "VAvatar",
	props: makeVAvatarProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { borderClasses } = useBorder(props);
		const { colorClasses, colorStyles, variantClasses } = useVariant(props);
		const { densityClasses } = useDensity(props);
		const { roundedClasses } = useRounded(props);
		const { sizeClasses, sizeStyles } = useSize(props);
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-avatar",
				{
					"v-avatar--start": props.start,
					"v-avatar--end": props.end
				},
				themeClasses.value,
				borderClasses.value,
				colorClasses.value,
				densityClasses.value,
				roundedClasses.value,
				sizeClasses.value,
				variantClasses.value,
				props.class
			],
			"style": [
				colorStyles.value,
				sizeStyles.value,
				props.style
			]
		}, { default: () => [!slots.default ? props.image ? createVNode(VImg, {
			"key": "image",
			"src": props.image,
			"alt": "",
			"cover": true
		}, null) : props.icon ? createVNode(VIcon, {
			"key": "icon",
			"icon": props.icon
		}, null) : props.text : createVNode(VDefaultsProvider, {
			"key": "content-defaults",
			"defaults": {
				VImg: {
					cover: true,
					src: props.image
				},
				VIcon: { icon: props.icon }
			}
		}, { default: () => [slots.default()] }), genOverlays(false, "v-avatar")] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VLabel/VLabel.js
var makeVLabelProps = propsFactory({
	text: String,
	onClick: EventProp(),
	...makeComponentProps(),
	...makeThemeProps()
}, "VLabel");
var VLabel = genericComponent()({
	name: "VLabel",
	props: makeVLabelProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode("label", {
			"class": [
				"v-label",
				{ "v-label--clickable": !!props.onClick },
				props.class
			],
			"style": props.style,
			"onClick": props.onClick
		}, [props.text, slots.default?.()]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSelectionControlGroup/VSelectionControlGroup.js
var VSelectionControlGroupSymbol = Symbol.for("vuetify:selection-control-group");
var makeSelectionControlGroupProps = propsFactory({
	color: String,
	disabled: {
		type: Boolean,
		default: null
	},
	defaultsTarget: String,
	error: Boolean,
	id: String,
	inline: Boolean,
	falseIcon: IconValue,
	trueIcon: IconValue,
	ripple: {
		type: [Boolean, Object],
		default: true
	},
	multiple: {
		type: Boolean,
		default: null
	},
	name: String,
	readonly: {
		type: Boolean,
		default: null
	},
	modelValue: null,
	type: String,
	valueComparator: {
		type: Function,
		default: deepEqual
	},
	...makeComponentProps(),
	...makeDensityProps(),
	...makeThemeProps()
}, "SelectionControlGroup");
var makeVSelectionControlGroupProps = propsFactory({ ...makeSelectionControlGroupProps({ defaultsTarget: "VSelectionControl" }) }, "VSelectionControlGroup");
var VSelectionControlGroup = genericComponent()({
	name: "VSelectionControlGroup",
	props: makeVSelectionControlGroupProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const modelValue = useProxiedModel(props, "modelValue");
		const uid = useId();
		const id = toRef(() => props.id || `v-selection-control-group-${uid}`);
		const name = toRef(() => props.name || id.value);
		const updateHandlers = /* @__PURE__ */ new Set();
		provide(VSelectionControlGroupSymbol, {
			modelValue,
			forceUpdate: () => {
				updateHandlers.forEach((fn) => fn());
			},
			onForceUpdate: (cb) => {
				updateHandlers.add(cb);
				onScopeDispose(() => {
					updateHandlers.delete(cb);
				});
			}
		});
		provideDefaults({ [props.defaultsTarget]: {
			color: toRef(() => props.color),
			disabled: toRef(() => props.disabled),
			density: toRef(() => props.density),
			error: toRef(() => props.error),
			inline: toRef(() => props.inline),
			modelValue,
			multiple: toRef(() => !!props.multiple || props.multiple == null && Array.isArray(modelValue.value)),
			name,
			falseIcon: toRef(() => props.falseIcon),
			trueIcon: toRef(() => props.trueIcon),
			readonly: toRef(() => props.readonly),
			ripple: toRef(() => props.ripple),
			type: toRef(() => props.type),
			valueComparator: toRef(() => props.valueComparator)
		} });
		useRender(() => createVNode("div", {
			"class": [
				"v-selection-control-group",
				{ "v-selection-control-group--inline": props.inline },
				props.class
			],
			"style": props.style,
			"role": props.type === "radio" ? "radiogroup" : void 0
		}, [slots.default?.()]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSelectionControl/VSelectionControl.js
var makeVSelectionControlProps = propsFactory({
	label: String,
	baseColor: String,
	trueValue: null,
	falseValue: null,
	value: null,
	...makeComponentProps(),
	...makeSelectionControlGroupProps()
}, "VSelectionControl");
function useSelectionControl(props) {
	const group = inject(VSelectionControlGroupSymbol, void 0);
	const { densityClasses } = useDensity(props);
	const modelValue = useProxiedModel(props, "modelValue");
	const trueValue = computed(() => props.trueValue !== void 0 ? props.trueValue : props.value !== void 0 ? props.value : true);
	const falseValue = computed(() => props.falseValue !== void 0 ? props.falseValue : false);
	const isMultiple = computed(() => !!props.multiple || props.multiple == null && Array.isArray(modelValue.value));
	const model = computed({
		get() {
			const val = group ? group.modelValue.value : modelValue.value;
			return isMultiple.value ? wrapInArray(val).some((v) => props.valueComparator(v, trueValue.value)) : props.valueComparator(val, trueValue.value);
		},
		set(val) {
			if (props.readonly) return;
			const currentValue = val ? trueValue.value : falseValue.value;
			let newVal = currentValue;
			if (isMultiple.value) newVal = val ? [...wrapInArray(modelValue.value), currentValue] : wrapInArray(modelValue.value).filter((item) => !props.valueComparator(item, trueValue.value));
			if (group) group.modelValue.value = newVal;
			else modelValue.value = newVal;
		}
	});
	const { textColorClasses, textColorStyles } = useTextColor(() => {
		if (props.error || props.disabled) return void 0;
		return model.value ? props.color : props.baseColor;
	});
	const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => {
		return model.value && !props.error && !props.disabled ? props.color : props.baseColor;
	});
	return {
		group,
		densityClasses,
		trueValue,
		falseValue,
		model,
		textColorClasses,
		textColorStyles,
		backgroundColorClasses,
		backgroundColorStyles,
		icon: computed(() => model.value ? props.trueIcon : props.falseIcon)
	};
}
var VSelectionControl = genericComponent()({
	name: "VSelectionControl",
	directives: { Ripple },
	inheritAttrs: false,
	props: makeVSelectionControlProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { group, densityClasses, icon, model, textColorClasses, textColorStyles, backgroundColorClasses, backgroundColorStyles, trueValue } = useSelectionControl(props);
		const uid = useId();
		const isFocused = shallowRef(false);
		const isFocusVisible = shallowRef(false);
		const input = ref();
		const id = toRef(() => props.id || `input-${uid}`);
		const isInteractive = toRef(() => !props.disabled && !props.readonly);
		group?.onForceUpdate(() => {
			if (input.value) input.value.checked = model.value;
		});
		function onFocus(e) {
			if (!isInteractive.value) return;
			isFocused.value = true;
			if (matchesSelector(e.target, ":focus-visible") !== false) isFocusVisible.value = true;
		}
		function onBlur() {
			isFocused.value = false;
			isFocusVisible.value = false;
		}
		function onClickLabel(e) {
			e.stopPropagation();
		}
		function onInput(e) {
			if (!isInteractive.value) {
				if (input.value) input.value.checked = model.value;
				return;
			}
			if (props.readonly && group) nextTick(() => group.forceUpdate());
			model.value = e.target.checked;
		}
		useRender(() => {
			const label = slots.label ? slots.label({
				label: props.label,
				props: { for: id.value }
			}) : props.label;
			const [rootAttrs, inputAttrs] = filterInputAttrs(attrs);
			const inputNode = createVNode("input", mergeProps({
				"ref": input,
				"checked": model.value,
				"disabled": !!props.disabled,
				"id": id.value,
				"onBlur": onBlur,
				"onFocus": onFocus,
				"onInput": onInput,
				"aria-disabled": !!props.disabled,
				"aria-label": props.label,
				"type": props.type,
				"value": trueValue.value,
				"name": props.name,
				"aria-checked": props.type === "checkbox" ? model.value : void 0
			}, inputAttrs), null);
			return createVNode("div", mergeProps({ "class": [
				"v-selection-control",
				{
					"v-selection-control--dirty": model.value,
					"v-selection-control--disabled": props.disabled,
					"v-selection-control--error": props.error,
					"v-selection-control--focused": isFocused.value,
					"v-selection-control--focus-visible": isFocusVisible.value,
					"v-selection-control--inline": props.inline
				},
				densityClasses.value,
				props.class
			] }, rootAttrs, { "style": props.style }), [createVNode("div", {
				"class": ["v-selection-control__wrapper", textColorClasses.value],
				"style": textColorStyles.value
			}, [slots.default?.({
				backgroundColorClasses,
				backgroundColorStyles
			}), withDirectives(createVNode("div", { "class": ["v-selection-control__input"] }, [slots.input?.({
				model,
				textColorClasses,
				textColorStyles,
				backgroundColorClasses,
				backgroundColorStyles,
				inputNode,
				icon: icon.value,
				props: {
					onFocus,
					onBlur,
					id: id.value
				}
			}) ?? createVNode(Fragment, null, [icon.value && createVNode(VIcon, {
				"key": "icon",
				"icon": icon.value
			}, null), inputNode])]), [[resolveDirective("ripple"), props.ripple && [
				!props.disabled && !props.readonly,
				null,
				["center", "circle"]
			]]])]), label && createVNode(VLabel, {
				"for": id.value,
				"onClick": onClickLabel
			}, { default: () => [label] })]);
		});
		return {
			isFocused,
			input
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCheckbox/VCheckboxBtn.js
var makeVCheckboxBtnProps = propsFactory({
	indeterminate: Boolean,
	indeterminateIcon: {
		type: IconValue,
		default: "$checkboxIndeterminate"
	},
	...makeVSelectionControlProps({
		falseIcon: "$checkboxOff",
		trueIcon: "$checkboxOn"
	})
}, "VCheckboxBtn");
var VCheckboxBtn = genericComponent()({
	name: "VCheckboxBtn",
	props: makeVCheckboxBtnProps(),
	emits: {
		"update:modelValue": (value) => true,
		"update:indeterminate": (value) => true
	},
	setup(props, _ref) {
		let { slots } = _ref;
		const indeterminate = useProxiedModel(props, "indeterminate");
		const model = useProxiedModel(props, "modelValue");
		function onChange(v) {
			if (indeterminate.value) indeterminate.value = false;
		}
		const falseIcon = toRef(() => {
			return indeterminate.value ? props.indeterminateIcon : props.falseIcon;
		});
		const trueIcon = toRef(() => {
			return indeterminate.value ? props.indeterminateIcon : props.trueIcon;
		});
		useRender(() => {
			return createVNode(VSelectionControl, mergeProps(omit(VSelectionControl.filterProps(props), ["modelValue"]), {
				"modelValue": model.value,
				"onUpdate:modelValue": [($event) => model.value = $event, onChange],
				"class": ["v-checkbox-btn", props.class],
				"style": props.style,
				"type": "checkbox",
				"falseIcon": falseIcon.value,
				"trueIcon": trueIcon.value,
				"aria-checked": indeterminate.value ? "mixed" : void 0
			}), slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VInput/InputIcon.js
function useInputIcon(props) {
	const { t } = useLocale();
	function InputIcon(_ref) {
		let { name, color } = _ref;
		const localeKey = {
			prepend: "prependAction",
			prependInner: "prependAction",
			append: "appendAction",
			appendInner: "appendAction",
			clear: "clear"
		}[name];
		const listener = props[`onClick:${name}`];
		function onKeydown(e) {
			if (e.key !== "Enter" && e.key !== " ") return;
			e.preventDefault();
			e.stopPropagation();
			callEvent(listener, new PointerEvent("click", e));
		}
		const label = listener && localeKey ? t(`$vuetify.input.${localeKey}`, props.label ?? "") : void 0;
		return createVNode(VIcon, {
			"icon": props[`${name}Icon`],
			"aria-label": label,
			"onClick": listener,
			"onKeydown": onKeydown,
			"color": color
		}, null);
	}
	return { InputIcon };
}
//#endregion
//#region node_modules/vuetify/lib/components/VMessages/VMessages.js
var makeVMessagesProps = propsFactory({
	active: Boolean,
	color: String,
	messages: {
		type: [Array, String],
		default: () => []
	},
	...makeComponentProps(),
	...makeTransitionProps({ transition: {
		component: VSlideYTransition,
		leaveAbsolute: true,
		group: true
	} })
}, "VMessages");
var VMessages = genericComponent()({
	name: "VMessages",
	props: makeVMessagesProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const messages = computed(() => wrapInArray(props.messages));
		const { textColorClasses, textColorStyles } = useTextColor(() => props.color);
		useRender(() => createVNode(MaybeTransition, {
			"transition": props.transition,
			"tag": "div",
			"class": [
				"v-messages",
				textColorClasses.value,
				props.class
			],
			"style": [textColorStyles.value, props.style]
		}, { default: () => [props.active && messages.value.map((message, i) => createVNode("div", {
			"class": "v-messages__message",
			"key": `${i}-${messages.value}`
		}, [slots.message ? slots.message({ message }) : message]))] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/focus.js
var makeFocusProps = propsFactory({
	focused: Boolean,
	"onUpdate:focused": EventProp()
}, "focus");
function useFocus(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	const isFocused = useProxiedModel(props, "focused");
	const focusClasses = toRef(() => {
		return { [`${name}--focused`]: isFocused.value };
	});
	function focus() {
		isFocused.value = true;
	}
	function blur() {
		isFocused.value = false;
	}
	return {
		focusClasses,
		isFocused,
		focus,
		blur
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/form.js
var FormKey = Symbol.for("vuetify:form");
var makeFormProps = propsFactory({
	disabled: Boolean,
	fastFail: Boolean,
	readonly: Boolean,
	modelValue: {
		type: Boolean,
		default: null
	},
	validateOn: {
		type: String,
		default: "input"
	}
}, "form");
function createForm(props) {
	const model = useProxiedModel(props, "modelValue");
	const isDisabled = toRef(() => props.disabled);
	const isReadonly = toRef(() => props.readonly);
	const isValidating = shallowRef(false);
	const items = ref([]);
	const errors = ref([]);
	async function validate() {
		const results = [];
		let valid = true;
		errors.value = [];
		isValidating.value = true;
		for (const item of items.value) {
			const itemErrorMessages = await item.validate();
			if (itemErrorMessages.length > 0) {
				valid = false;
				results.push({
					id: item.id,
					errorMessages: itemErrorMessages
				});
			}
			if (!valid && props.fastFail) break;
		}
		errors.value = results;
		isValidating.value = false;
		return {
			valid,
			errors: errors.value
		};
	}
	function reset() {
		items.value.forEach((item) => item.reset());
	}
	function resetValidation() {
		items.value.forEach((item) => item.resetValidation());
	}
	watch(items, () => {
		let valid = 0;
		let invalid = 0;
		const results = [];
		for (const item of items.value) if (item.isValid === false) {
			invalid++;
			results.push({
				id: item.id,
				errorMessages: item.errorMessages
			});
		} else if (item.isValid === true) valid++;
		errors.value = results;
		model.value = invalid > 0 ? false : valid === items.value.length ? true : null;
	}, {
		deep: true,
		flush: "post"
	});
	provide(FormKey, {
		register: (_ref) => {
			let { id, vm, validate, reset, resetValidation } = _ref;
			if (items.value.some((item) => item.id === id)) consoleWarn(`Duplicate input name "${id}"`);
			items.value.push({
				id,
				validate,
				reset,
				resetValidation,
				vm: markRaw(vm),
				isValid: null,
				errorMessages: []
			});
		},
		unregister: (id) => {
			items.value = items.value.filter((item) => {
				return item.id !== id;
			});
		},
		update: (id, isValid, errorMessages) => {
			const found = items.value.find((item) => item.id === id);
			if (!found) return;
			found.isValid = isValid;
			found.errorMessages = errorMessages;
		},
		isDisabled,
		isReadonly,
		isValidating,
		isValid: model,
		items,
		validateOn: toRef(() => props.validateOn)
	});
	return {
		errors,
		isDisabled,
		isReadonly,
		isValidating,
		isValid: model,
		items,
		validate,
		reset,
		resetValidation
	};
}
function useForm$1(props) {
	const form = inject(FormKey, null);
	return {
		...form,
		isReadonly: computed(() => !!(props?.readonly ?? form?.isReadonly.value)),
		isDisabled: computed(() => !!(props?.disabled ?? form?.isDisabled.value))
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/validation.js
var makeValidationProps = propsFactory({
	disabled: {
		type: Boolean,
		default: null
	},
	error: Boolean,
	errorMessages: {
		type: [Array, String],
		default: () => []
	},
	maxErrors: {
		type: [Number, String],
		default: 1
	},
	name: String,
	label: String,
	readonly: {
		type: Boolean,
		default: null
	},
	rules: {
		type: Array,
		default: () => []
	},
	modelValue: null,
	validateOn: String,
	validationValue: null,
	...makeFocusProps()
}, "validation");
function useValidation(props) {
	let name = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : getCurrentInstanceName();
	let id = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : useId();
	const model = useProxiedModel(props, "modelValue");
	const validationModel = computed(() => props.validationValue === void 0 ? model.value : props.validationValue);
	const form = useForm$1(props);
	const internalErrorMessages = ref([]);
	const isPristine = shallowRef(true);
	const isDirty = computed(() => !!(wrapInArray(model.value === "" ? null : model.value).length || wrapInArray(validationModel.value === "" ? null : validationModel.value).length));
	const errorMessages = computed(() => {
		return props.errorMessages?.length ? wrapInArray(props.errorMessages).concat(internalErrorMessages.value).slice(0, Math.max(0, Number(props.maxErrors))) : internalErrorMessages.value;
	});
	const validateOn = computed(() => {
		let value = (props.validateOn ?? form.validateOn?.value) || "input";
		if (value === "lazy") value = "input lazy";
		if (value === "eager") value = "input eager";
		const set = new Set(value?.split(" ") ?? []);
		return {
			input: set.has("input"),
			blur: set.has("blur") || set.has("input") || set.has("invalid-input"),
			invalidInput: set.has("invalid-input"),
			lazy: set.has("lazy"),
			eager: set.has("eager")
		};
	});
	const isValid = computed(() => {
		if (props.error || props.errorMessages?.length) return false;
		if (!props.rules.length) return true;
		if (isPristine.value) return internalErrorMessages.value.length || validateOn.value.lazy ? null : true;
		else return !internalErrorMessages.value.length;
	});
	const isValidating = shallowRef(false);
	const validationClasses = computed(() => {
		return {
			[`${name}--error`]: isValid.value === false,
			[`${name}--dirty`]: isDirty.value,
			[`${name}--disabled`]: form.isDisabled.value,
			[`${name}--readonly`]: form.isReadonly.value
		};
	});
	const vm = getCurrentInstance$1("validation");
	const uid = computed(() => props.name ?? unref(id));
	onBeforeMount(() => {
		form.register?.({
			id: uid.value,
			vm,
			validate,
			reset,
			resetValidation
		});
	});
	onBeforeUnmount(() => {
		form.unregister?.(uid.value);
	});
	onMounted(async () => {
		if (!validateOn.value.lazy) await validate(!validateOn.value.eager);
		form.update?.(uid.value, isValid.value, errorMessages.value);
	});
	useToggleScope(() => validateOn.value.input || validateOn.value.invalidInput && isValid.value === false, () => {
		watch(validationModel, () => {
			if (validationModel.value != null) validate();
			else if (props.focused) {
				const unwatch = watch(() => props.focused, (val) => {
					if (!val) validate();
					unwatch();
				});
			}
		});
	});
	useToggleScope(() => validateOn.value.blur, () => {
		watch(() => props.focused, (val) => {
			if (!val) validate();
		});
	});
	watch([isValid, errorMessages], () => {
		form.update?.(uid.value, isValid.value, errorMessages.value);
	});
	async function reset() {
		model.value = null;
		await nextTick();
		await resetValidation();
	}
	async function resetValidation() {
		isPristine.value = true;
		if (!validateOn.value.lazy) await validate(!validateOn.value.eager);
		else internalErrorMessages.value = [];
	}
	async function validate() {
		let silent = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : false;
		const results = [];
		isValidating.value = true;
		for (const rule of props.rules) {
			if (results.length >= Number(props.maxErrors ?? 1)) break;
			const result = await (typeof rule === "function" ? rule : () => rule)(validationModel.value);
			if (result === true) continue;
			if (result !== false && typeof result !== "string") {
				console.warn(`${result} is not a valid value. Rule functions must return boolean true or a string.`);
				continue;
			}
			results.push(result || "");
		}
		internalErrorMessages.value = results;
		isValidating.value = false;
		isPristine.value = silent;
		return internalErrorMessages.value;
	}
	return {
		errorMessages,
		isDirty,
		isDisabled: form.isDisabled,
		isReadonly: form.isReadonly,
		isPristine,
		isValid,
		isValidating,
		reset,
		resetValidation,
		validate,
		validationClasses
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VInput/VInput.js
var makeVInputProps = propsFactory({
	id: String,
	appendIcon: IconValue,
	baseColor: String,
	centerAffix: {
		type: Boolean,
		default: true
	},
	color: String,
	glow: Boolean,
	iconColor: [Boolean, String],
	prependIcon: IconValue,
	hideDetails: [Boolean, String],
	hideSpinButtons: Boolean,
	hint: String,
	persistentHint: Boolean,
	messages: {
		type: [Array, String],
		default: () => []
	},
	direction: {
		type: String,
		default: "horizontal",
		validator: (v) => ["horizontal", "vertical"].includes(v)
	},
	"onClick:prepend": EventProp(),
	"onClick:append": EventProp(),
	...makeComponentProps(),
	...makeDensityProps(),
	...pick(makeDimensionProps(), [
		"maxWidth",
		"minWidth",
		"width"
	]),
	...makeThemeProps(),
	...makeValidationProps()
}, "VInput");
var VInput = genericComponent()({
	name: "VInput",
	props: { ...makeVInputProps() },
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { attrs, slots, emit } = _ref;
		const { densityClasses } = useDensity(props);
		const { dimensionStyles } = useDimension(props);
		const { themeClasses } = provideTheme(props);
		const { rtlClasses } = useRtl();
		const { InputIcon } = useInputIcon(props);
		const uid = useId();
		const id = computed(() => props.id || `input-${uid}`);
		const messagesId = computed(() => `${id.value}-messages`);
		const { errorMessages, isDirty, isDisabled, isReadonly, isPristine, isValid, isValidating, reset, resetValidation, validate, validationClasses } = useValidation(props, "v-input", id);
		const slotProps = computed(() => ({
			id,
			messagesId,
			isDirty,
			isDisabled,
			isReadonly,
			isPristine,
			isValid,
			isValidating,
			reset,
			resetValidation,
			validate
		}));
		const color = toRef(() => {
			return props.error || props.disabled ? void 0 : props.focused ? props.color : props.baseColor;
		});
		const iconColor = toRef(() => {
			if (!props.iconColor) return void 0;
			return props.iconColor === true ? color.value : props.iconColor;
		});
		const messages = computed(() => {
			if (props.errorMessages?.length || !isPristine.value && errorMessages.value.length) return errorMessages.value;
			else if (props.hint && (props.persistentHint || props.focused)) return props.hint;
			else return props.messages;
		});
		useRender(() => {
			const hasPrepend = !!(slots.prepend || props.prependIcon);
			const hasAppend = !!(slots.append || props.appendIcon);
			const hasMessages = messages.value.length > 0;
			const hasDetails = !props.hideDetails || props.hideDetails === "auto" && (hasMessages || !!slots.details);
			return createVNode("div", {
				"class": [
					"v-input",
					`v-input--${props.direction}`,
					{
						"v-input--center-affix": props.centerAffix,
						"v-input--focused": props.focused,
						"v-input--glow": props.glow,
						"v-input--hide-spin-buttons": props.hideSpinButtons
					},
					densityClasses.value,
					themeClasses.value,
					rtlClasses.value,
					validationClasses.value,
					props.class
				],
				"style": [dimensionStyles.value, props.style]
			}, [
				hasPrepend && createVNode("div", {
					"key": "prepend",
					"class": "v-input__prepend"
				}, [slots.prepend?.(slotProps.value), props.prependIcon && createVNode(InputIcon, {
					"key": "prepend-icon",
					"name": "prepend",
					"color": iconColor.value
				}, null)]),
				slots.default && createVNode("div", { "class": "v-input__control" }, [slots.default?.(slotProps.value)]),
				hasAppend && createVNode("div", {
					"key": "append",
					"class": "v-input__append"
				}, [props.appendIcon && createVNode(InputIcon, {
					"key": "append-icon",
					"name": "append",
					"color": iconColor.value
				}, null), slots.append?.(slotProps.value)]),
				hasDetails && createVNode("div", {
					"id": messagesId.value,
					"class": "v-input__details",
					"role": "alert",
					"aria-live": "polite"
				}, [createVNode(VMessages, {
					"active": hasMessages,
					"messages": messages.value
				}, { message: slots.message }), slots.details?.(slotProps.value)])
			]);
		});
		return {
			reset,
			resetValidation,
			validate,
			isValid,
			errorMessages
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCheckbox/VCheckbox.js
var makeVCheckboxProps = propsFactory({
	...makeVInputProps(),
	...omit(makeVCheckboxBtnProps(), ["inline"])
}, "VCheckbox");
var VCheckbox = genericComponent()({
	name: "VCheckbox",
	inheritAttrs: false,
	props: makeVCheckboxProps(),
	emits: {
		"update:modelValue": (value) => true,
		"update:focused": (focused) => true
	},
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const { isFocused, focus, blur } = useFocus(props);
		const uid = useId();
		useRender(() => {
			const [rootAttrs, controlAttrs] = filterInputAttrs(attrs);
			const inputProps = VInput.filterProps(props);
			const checkboxProps = VCheckboxBtn.filterProps(props);
			return createVNode(VInput, mergeProps({ "class": ["v-checkbox", props.class] }, rootAttrs, inputProps, {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"id": props.id || `checkbox-${uid}`,
				"focused": isFocused.value,
				"style": props.style
			}), {
				...slots,
				default: (_ref2) => {
					let { id, messagesId, isDisabled, isReadonly, isValid } = _ref2;
					return createVNode(VCheckboxBtn, mergeProps(checkboxProps, {
						"id": id.value,
						"aria-describedby": messagesId.value,
						"disabled": isDisabled.value,
						"readonly": isReadonly.value
					}, controlAttrs, {
						"error": isValid.value === false,
						"modelValue": model.value,
						"onUpdate:modelValue": ($event) => model.value = $event,
						"onFocus": focus,
						"onBlur": blur
					}), slots);
				}
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSlideGroup/helpers.js
function calculateUpdatedTarget(_ref) {
	let { selectedElement, containerElement, isRtl, isHorizontal } = _ref;
	const containerSize = getOffsetSize(isHorizontal, containerElement);
	const scrollPosition = getScrollPosition(isHorizontal, isRtl, containerElement);
	const childrenSize = getOffsetSize(isHorizontal, selectedElement);
	const childrenStartPosition = getOffsetPosition(isHorizontal, selectedElement);
	const additionalOffset = childrenSize * .4;
	if (scrollPosition > childrenStartPosition) return childrenStartPosition - additionalOffset;
	else if (scrollPosition + containerSize < childrenStartPosition + childrenSize) return childrenStartPosition - containerSize + childrenSize + additionalOffset;
	return scrollPosition;
}
function calculateCenteredTarget(_ref2) {
	let { selectedElement, containerElement, isHorizontal } = _ref2;
	const containerOffsetSize = getOffsetSize(isHorizontal, containerElement);
	const childrenOffsetPosition = getOffsetPosition(isHorizontal, selectedElement);
	const childrenOffsetSize = getOffsetSize(isHorizontal, selectedElement);
	return childrenOffsetPosition - containerOffsetSize / 2 + childrenOffsetSize / 2;
}
function getScrollSize(isHorizontal, element) {
	return element?.[isHorizontal ? "scrollWidth" : "scrollHeight"] || 0;
}
function getClientSize(isHorizontal, element) {
	return element?.[isHorizontal ? "clientWidth" : "clientHeight"] || 0;
}
function getScrollPosition(isHorizontal, rtl, element) {
	if (!element) return 0;
	const { scrollLeft, offsetWidth, scrollWidth } = element;
	if (isHorizontal) return rtl ? scrollWidth - offsetWidth + scrollLeft : scrollLeft;
	return element.scrollTop;
}
function getOffsetSize(isHorizontal, element) {
	return element?.[isHorizontal ? "offsetWidth" : "offsetHeight"] || 0;
}
function getOffsetPosition(isHorizontal, element) {
	return element?.[isHorizontal ? "offsetLeft" : "offsetTop"] || 0;
}
//#endregion
//#region node_modules/vuetify/lib/components/VSlideGroup/VSlideGroup.js
var VSlideGroupSymbol = Symbol.for("vuetify:v-slide-group");
var makeVSlideGroupProps = propsFactory({
	centerActive: Boolean,
	direction: {
		type: String,
		default: "horizontal"
	},
	symbol: {
		type: null,
		default: VSlideGroupSymbol
	},
	nextIcon: {
		type: IconValue,
		default: "$next"
	},
	prevIcon: {
		type: IconValue,
		default: "$prev"
	},
	showArrows: {
		type: [Boolean, String],
		validator: (v) => typeof v === "boolean" || [
			"always",
			"desktop",
			"mobile"
		].includes(v)
	},
	...makeComponentProps(),
	...makeDisplayProps({ mobile: null }),
	...makeTagProps(),
	...makeGroupProps({ selectedClass: "v-slide-group-item--active" })
}, "VSlideGroup");
var VSlideGroup = genericComponent()({
	name: "VSlideGroup",
	props: makeVSlideGroupProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { isRtl } = useRtl();
		const { displayClasses, mobile } = useDisplay(props);
		const group = useGroup(props, props.symbol);
		const isOverflowing = shallowRef(false);
		const scrollOffset = shallowRef(0);
		const containerSize = shallowRef(0);
		const contentSize = shallowRef(0);
		const isHorizontal = computed(() => props.direction === "horizontal");
		const { resizeRef: containerRef, contentRect: containerRect } = useResizeObserver();
		const { resizeRef: contentRef, contentRect } = useResizeObserver();
		const goTo = useGoTo();
		const goToOptions = computed(() => {
			return {
				container: containerRef.el,
				duration: 200,
				easing: "easeOutQuart"
			};
		});
		const firstSelectedIndex = computed(() => {
			if (!group.selected.value.length) return -1;
			return group.items.value.findIndex((item) => item.id === group.selected.value[0]);
		});
		const lastSelectedIndex = computed(() => {
			if (!group.selected.value.length) return -1;
			return group.items.value.findIndex((item) => item.id === group.selected.value[group.selected.value.length - 1]);
		});
		if (IN_BROWSER) {
			let frame = -1;
			watch(() => [
				group.selected.value,
				containerRect.value,
				contentRect.value,
				isHorizontal.value
			], () => {
				cancelAnimationFrame(frame);
				frame = requestAnimationFrame(() => {
					if (containerRect.value && contentRect.value) {
						const sizeProperty = isHorizontal.value ? "width" : "height";
						containerSize.value = containerRect.value[sizeProperty];
						contentSize.value = contentRect.value[sizeProperty];
						isOverflowing.value = containerSize.value + 1 < contentSize.value;
					}
					if (firstSelectedIndex.value >= 0 && contentRef.el) {
						const selectedElement = contentRef.el.children[lastSelectedIndex.value];
						scrollToChildren(selectedElement, props.centerActive);
					}
				});
			});
		}
		const isFocused = shallowRef(false);
		function scrollToChildren(children, center) {
			let target = 0;
			if (center) target = calculateCenteredTarget({
				containerElement: containerRef.el,
				isHorizontal: isHorizontal.value,
				selectedElement: children
			});
			else target = calculateUpdatedTarget({
				containerElement: containerRef.el,
				isHorizontal: isHorizontal.value,
				isRtl: isRtl.value,
				selectedElement: children
			});
			scrollToPosition(target);
		}
		function scrollToPosition(newPosition) {
			if (!IN_BROWSER || !containerRef.el) return;
			const offsetSize = getOffsetSize(isHorizontal.value, containerRef.el);
			const scrollPosition = getScrollPosition(isHorizontal.value, isRtl.value, containerRef.el);
			if (getScrollSize(isHorizontal.value, containerRef.el) <= offsetSize || Math.abs(newPosition - scrollPosition) < 16) return;
			if (isHorizontal.value && isRtl.value && containerRef.el) {
				const { scrollWidth, offsetWidth: containerWidth } = containerRef.el;
				newPosition = scrollWidth - containerWidth - newPosition;
			}
			if (isHorizontal.value) goTo.horizontal(newPosition, goToOptions.value);
			else goTo(newPosition, goToOptions.value);
		}
		function onScroll(e) {
			const { scrollTop, scrollLeft } = e.target;
			scrollOffset.value = isHorizontal.value ? scrollLeft : scrollTop;
		}
		function onFocusin(e) {
			isFocused.value = true;
			if (!isOverflowing.value || !contentRef.el) return;
			for (const el of e.composedPath()) for (const item of contentRef.el.children) if (item === el) {
				scrollToChildren(item);
				return;
			}
		}
		function onFocusout(e) {
			isFocused.value = false;
		}
		let ignoreFocusEvent = false;
		function onFocus(e) {
			if (!ignoreFocusEvent && !isFocused.value && !(e.relatedTarget && contentRef.el?.contains(e.relatedTarget))) focus();
			ignoreFocusEvent = false;
		}
		function onFocusAffixes() {
			ignoreFocusEvent = true;
		}
		function onKeydown(e) {
			if (!contentRef.el) return;
			function toFocus(location) {
				e.preventDefault();
				focus(location);
			}
			if (isHorizontal.value) {
				if (e.key === "ArrowRight") toFocus(isRtl.value ? "prev" : "next");
				else if (e.key === "ArrowLeft") toFocus(isRtl.value ? "next" : "prev");
			} else if (e.key === "ArrowDown") toFocus("next");
			else if (e.key === "ArrowUp") toFocus("prev");
			if (e.key === "Home") toFocus("first");
			else if (e.key === "End") toFocus("last");
		}
		function getSiblingElement(el, location) {
			if (!el) return void 0;
			let sibling = el;
			do
				sibling = sibling?.[location === "next" ? "nextElementSibling" : "previousElementSibling"];
			while (sibling?.hasAttribute("disabled"));
			return sibling;
		}
		function focus(location) {
			if (!contentRef.el) return;
			let el;
			if (!location) el = focusableChildren(contentRef.el)[0];
			else if (location === "next") {
				el = getSiblingElement(contentRef.el.querySelector(":focus"), location);
				if (!el) return focus("first");
			} else if (location === "prev") {
				el = getSiblingElement(contentRef.el.querySelector(":focus"), location);
				if (!el) return focus("last");
			} else if (location === "first") {
				el = contentRef.el.firstElementChild;
				if (el?.hasAttribute("disabled")) el = getSiblingElement(el, "next");
			} else if (location === "last") {
				el = contentRef.el.lastElementChild;
				if (el?.hasAttribute("disabled")) el = getSiblingElement(el, "prev");
			}
			if (el) el.focus({ preventScroll: true });
		}
		function scrollTo(location) {
			const direction = isHorizontal.value && isRtl.value ? -1 : 1;
			const offsetStep = (location === "prev" ? -direction : direction) * containerSize.value;
			let newPosition = scrollOffset.value + offsetStep;
			if (isHorizontal.value && isRtl.value && containerRef.el) {
				const { scrollWidth, offsetWidth: containerWidth } = containerRef.el;
				newPosition += scrollWidth - containerWidth;
			}
			scrollToPosition(newPosition);
		}
		const slotProps = computed(() => ({
			next: group.next,
			prev: group.prev,
			select: group.select,
			isSelected: group.isSelected
		}));
		const hasAffixes = computed(() => {
			switch (props.showArrows) {
				case "always": return true;
				case "desktop": return !mobile.value;
				case true: return isOverflowing.value || Math.abs(scrollOffset.value) > 0;
				case "mobile": return mobile.value || isOverflowing.value || Math.abs(scrollOffset.value) > 0;
				default: return !mobile.value && (isOverflowing.value || Math.abs(scrollOffset.value) > 0);
			}
		});
		const hasPrev = computed(() => {
			return Math.abs(scrollOffset.value) > 1;
		});
		const hasNext = computed(() => {
			if (!containerRef.value) return false;
			return getScrollSize(isHorizontal.value, containerRef.el) - getClientSize(isHorizontal.value, containerRef.el) - Math.abs(scrollOffset.value) > 1;
		});
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-slide-group",
				{
					"v-slide-group--vertical": !isHorizontal.value,
					"v-slide-group--has-affixes": hasAffixes.value,
					"v-slide-group--is-overflowing": isOverflowing.value
				},
				displayClasses.value,
				props.class
			],
			"style": props.style,
			"tabindex": isFocused.value || group.selected.value.length ? -1 : 0,
			"onFocus": onFocus
		}, { default: () => [
			hasAffixes.value && createVNode("div", {
				"key": "prev",
				"class": ["v-slide-group__prev", { "v-slide-group__prev--disabled": !hasPrev.value }],
				"onMousedown": onFocusAffixes,
				"onClick": () => hasPrev.value && scrollTo("prev")
			}, [slots.prev?.(slotProps.value) ?? createVNode(VFadeTransition, null, { default: () => [createVNode(VIcon, { "icon": isRtl.value ? props.nextIcon : props.prevIcon }, null)] })]),
			createVNode("div", {
				"key": "container",
				"ref": containerRef,
				"class": "v-slide-group__container",
				"onScroll": onScroll
			}, [createVNode("div", {
				"ref": contentRef,
				"class": "v-slide-group__content",
				"onFocusin": onFocusin,
				"onFocusout": onFocusout,
				"onKeydown": onKeydown
			}, [slots.default?.(slotProps.value)])]),
			hasAffixes.value && createVNode("div", {
				"key": "next",
				"class": ["v-slide-group__next", { "v-slide-group__next--disabled": !hasNext.value }],
				"onMousedown": onFocusAffixes,
				"onClick": () => hasNext.value && scrollTo("next")
			}, [slots.next?.(slotProps.value) ?? createVNode(VFadeTransition, null, { default: () => [createVNode(VIcon, { "icon": isRtl.value ? props.prevIcon : props.nextIcon }, null)] })])
		] }));
		return {
			selected: group.selected,
			scrollTo,
			scrollOffset,
			focus,
			hasPrev,
			hasNext
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VChipGroup/VChipGroup.js
var VChipGroupSymbol = Symbol.for("vuetify:v-chip-group");
var makeVChipGroupProps = propsFactory({
	baseColor: String,
	column: Boolean,
	filter: Boolean,
	valueComparator: {
		type: Function,
		default: deepEqual
	},
	...makeVSlideGroupProps(),
	...makeComponentProps(),
	...makeGroupProps({ selectedClass: "v-chip--selected" }),
	...makeTagProps(),
	...makeThemeProps(),
	...makeVariantProps({ variant: "tonal" })
}, "VChipGroup");
var VChipGroup = genericComponent()({
	name: "VChipGroup",
	props: makeVChipGroupProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { isSelected, select, next, prev, selected } = useGroup(props, VChipGroupSymbol);
		provideDefaults({ VChip: {
			baseColor: toRef(() => props.baseColor),
			color: toRef(() => props.color),
			disabled: toRef(() => props.disabled),
			filter: toRef(() => props.filter),
			variant: toRef(() => props.variant)
		} });
		useRender(() => {
			return createVNode(VSlideGroup, mergeProps(VSlideGroup.filterProps(props), {
				"class": [
					"v-chip-group",
					{ "v-chip-group--column": props.column },
					themeClasses.value,
					props.class
				],
				"style": props.style
			}), { default: () => [slots.default?.({
				isSelected,
				select,
				next,
				prev,
				selected: selected.value
			})] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VChip/VChip.js
var makeVChipProps = propsFactory({
	activeClass: String,
	appendAvatar: String,
	appendIcon: IconValue,
	baseColor: String,
	closable: Boolean,
	closeIcon: {
		type: IconValue,
		default: "$delete"
	},
	closeLabel: {
		type: String,
		default: "$vuetify.close"
	},
	draggable: Boolean,
	filter: Boolean,
	filterIcon: {
		type: IconValue,
		default: "$complete"
	},
	label: Boolean,
	link: {
		type: Boolean,
		default: void 0
	},
	pill: Boolean,
	prependAvatar: String,
	prependIcon: IconValue,
	ripple: {
		type: [Boolean, Object],
		default: true
	},
	text: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	modelValue: {
		type: Boolean,
		default: true
	},
	onClick: EventProp(),
	onClickOnce: EventProp(),
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeElevationProps(),
	...makeGroupItemProps(),
	...makeRoundedProps(),
	...makeRouterProps(),
	...makeSizeProps(),
	...makeTagProps({ tag: "span" }),
	...makeThemeProps(),
	...makeVariantProps({ variant: "tonal" })
}, "VChip");
var VChip = genericComponent()({
	name: "VChip",
	directives: { Ripple },
	props: makeVChipProps(),
	emits: {
		"click:close": (e) => true,
		"update:modelValue": (value) => true,
		"group:selected": (val) => true,
		click: (e) => true
	},
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const { t } = useLocale();
		const { borderClasses } = useBorder(props);
		const { densityClasses } = useDensity(props);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		const { sizeClasses } = useSize(props);
		const { themeClasses } = provideTheme(props);
		const isActive = useProxiedModel(props, "modelValue");
		const group = useGroupItem(props, VChipGroupSymbol, false);
		const link = useLink(props, attrs);
		const isLink = toRef(() => props.link !== false && link.isLink.value);
		const isClickable = computed(() => !props.disabled && props.link !== false && (!!group || props.link || link.isClickable.value));
		const closeProps = toRef(() => ({
			"aria-label": t(props.closeLabel),
			onClick(e) {
				e.preventDefault();
				e.stopPropagation();
				isActive.value = false;
				emit("click:close", e);
			}
		}));
		const { colorClasses, colorStyles, variantClasses } = useVariant(() => {
			return {
				color: !group || group.isSelected.value ? props.color ?? props.baseColor : props.baseColor,
				variant: props.variant
			};
		});
		function onClick(e) {
			emit("click", e);
			if (!isClickable.value) return;
			link.navigate?.(e);
			group?.toggle();
		}
		function onKeyDown(e) {
			if (e.key === "Enter" || e.key === " ") {
				e.preventDefault();
				onClick(e);
			}
		}
		return () => {
			const Tag = link.isLink.value ? "a" : props.tag;
			const hasAppendMedia = !!(props.appendIcon || props.appendAvatar);
			const hasAppend = !!(hasAppendMedia || slots.append);
			const hasClose = !!(slots.close || props.closable);
			const hasFilter = !!(slots.filter || props.filter) && group;
			const hasPrependMedia = !!(props.prependIcon || props.prependAvatar);
			const hasPrepend = !!(hasPrependMedia || slots.prepend);
			return isActive.value && withDirectives(createVNode(Tag, mergeProps({
				"class": [
					"v-chip",
					{
						"v-chip--disabled": props.disabled,
						"v-chip--label": props.label,
						"v-chip--link": isClickable.value,
						"v-chip--filter": hasFilter,
						"v-chip--pill": props.pill,
						[`${props.activeClass}`]: props.activeClass && link.isActive?.value
					},
					themeClasses.value,
					borderClasses.value,
					colorClasses.value,
					densityClasses.value,
					elevationClasses.value,
					roundedClasses.value,
					sizeClasses.value,
					variantClasses.value,
					group?.selectedClass.value,
					props.class
				],
				"style": [colorStyles.value, props.style],
				"disabled": props.disabled || void 0,
				"draggable": props.draggable,
				"tabindex": isClickable.value ? 0 : void 0,
				"onClick": onClick,
				"onKeydown": isClickable.value && !isLink.value && onKeyDown
			}, link.linkProps), { default: () => [
				genOverlays(isClickable.value, "v-chip"),
				hasFilter && createVNode(VExpandXTransition, { "key": "filter" }, { default: () => [withDirectives(createVNode("div", { "class": "v-chip__filter" }, [!slots.filter ? createVNode(VIcon, {
					"key": "filter-icon",
					"icon": props.filterIcon
				}, null) : createVNode(VDefaultsProvider, {
					"key": "filter-defaults",
					"disabled": !props.filterIcon,
					"defaults": { VIcon: { icon: props.filterIcon } }
				}, slots.filter)]), [[vShow, group.isSelected.value]])] }),
				hasPrepend && createVNode("div", {
					"key": "prepend",
					"class": "v-chip__prepend"
				}, [!slots.prepend ? createVNode(Fragment, null, [props.prependIcon && createVNode(VIcon, {
					"key": "prepend-icon",
					"icon": props.prependIcon,
					"start": true
				}, null), props.prependAvatar && createVNode(VAvatar, {
					"key": "prepend-avatar",
					"image": props.prependAvatar,
					"start": true
				}, null)]) : createVNode(VDefaultsProvider, {
					"key": "prepend-defaults",
					"disabled": !hasPrependMedia,
					"defaults": {
						VAvatar: {
							image: props.prependAvatar,
							start: true
						},
						VIcon: {
							icon: props.prependIcon,
							start: true
						}
					}
				}, slots.prepend)]),
				createVNode("div", {
					"class": "v-chip__content",
					"data-no-activator": ""
				}, [slots.default?.({
					isSelected: group?.isSelected.value,
					selectedClass: group?.selectedClass.value,
					select: group?.select,
					toggle: group?.toggle,
					value: group?.value.value,
					disabled: props.disabled
				}) ?? toDisplayString(props.text)]),
				hasAppend && createVNode("div", {
					"key": "append",
					"class": "v-chip__append"
				}, [!slots.append ? createVNode(Fragment, null, [props.appendIcon && createVNode(VIcon, {
					"key": "append-icon",
					"end": true,
					"icon": props.appendIcon
				}, null), props.appendAvatar && createVNode(VAvatar, {
					"key": "append-avatar",
					"end": true,
					"image": props.appendAvatar
				}, null)]) : createVNode(VDefaultsProvider, {
					"key": "append-defaults",
					"disabled": !hasAppendMedia,
					"defaults": {
						VAvatar: {
							end: true,
							image: props.appendAvatar
						},
						VIcon: {
							end: true,
							icon: props.appendIcon
						}
					}
				}, slots.append)]),
				hasClose && createVNode("button", mergeProps({
					"key": "close",
					"class": "v-chip__close",
					"type": "button",
					"data-testid": "close-chip"
				}, closeProps.value), [!slots.close ? createVNode(VIcon, {
					"key": "close-icon",
					"icon": props.closeIcon,
					"size": "x-small"
				}, null) : createVNode(VDefaultsProvider, {
					"key": "close-defaults",
					"defaults": { VIcon: {
						icon: props.closeIcon,
						size: "x-small"
					} }
				}, slots.close)])
			] }), [[
				resolveDirective("ripple"),
				isClickable.value && props.ripple,
				null
			]]);
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VList/list.js
var ListKey = Symbol.for("vuetify:list");
function createList() {
	const parent = inject(ListKey, {
		hasPrepend: shallowRef(false),
		updateHasPrepend: () => null
	});
	const data = {
		hasPrepend: shallowRef(false),
		updateHasPrepend: (value) => {
			if (value) data.hasPrepend.value = value;
		}
	};
	provide(ListKey, data);
	return parent;
}
function useList() {
	return inject(ListKey, null);
}
//#endregion
//#region node_modules/vuetify/lib/composables/nested/activeStrategies.js
var independentActiveStrategy = (mandatory) => {
	const strategy = {
		activate: (_ref) => {
			let { id, value, activated } = _ref;
			id = toRaw(id);
			if (mandatory && !value && activated.size === 1 && activated.has(id)) return activated;
			if (value) activated.add(id);
			else activated.delete(id);
			return activated;
		},
		in: (v, children, parents) => {
			let set = /* @__PURE__ */ new Set();
			if (v != null) for (const id of wrapInArray(v)) set = strategy.activate({
				id,
				value: true,
				activated: new Set(set),
				children,
				parents
			});
			return set;
		},
		out: (v) => {
			return Array.from(v);
		}
	};
	return strategy;
};
var independentSingleActiveStrategy = (mandatory) => {
	const parentStrategy = independentActiveStrategy(mandatory);
	return {
		activate: (_ref2) => {
			let { activated, id, ...rest } = _ref2;
			id = toRaw(id);
			const singleSelected = activated.has(id) ? new Set([id]) : /* @__PURE__ */ new Set();
			return parentStrategy.activate({
				...rest,
				id,
				activated: singleSelected
			});
		},
		in: (v, children, parents) => {
			let set = /* @__PURE__ */ new Set();
			if (v != null) {
				const arr = wrapInArray(v);
				if (arr.length) set = parentStrategy.in(arr.slice(0, 1), children, parents);
			}
			return set;
		},
		out: (v, children, parents) => {
			return parentStrategy.out(v, children, parents);
		}
	};
};
var leafActiveStrategy = (mandatory) => {
	const parentStrategy = independentActiveStrategy(mandatory);
	return {
		activate: (_ref3) => {
			let { id, activated, children, ...rest } = _ref3;
			id = toRaw(id);
			if (children.has(id)) return activated;
			return parentStrategy.activate({
				id,
				activated,
				children,
				...rest
			});
		},
		in: parentStrategy.in,
		out: parentStrategy.out
	};
};
var leafSingleActiveStrategy = (mandatory) => {
	const parentStrategy = independentSingleActiveStrategy(mandatory);
	return {
		activate: (_ref4) => {
			let { id, activated, children, ...rest } = _ref4;
			id = toRaw(id);
			if (children.has(id)) return activated;
			return parentStrategy.activate({
				id,
				activated,
				children,
				...rest
			});
		},
		in: parentStrategy.in,
		out: parentStrategy.out
	};
};
//#endregion
//#region node_modules/vuetify/lib/composables/nested/openStrategies.js
var singleOpenStrategy = {
	open: (_ref) => {
		let { id, value, opened, parents } = _ref;
		if (value) {
			const newOpened = /* @__PURE__ */ new Set();
			newOpened.add(id);
			let parent = parents.get(id);
			while (parent != null) {
				newOpened.add(parent);
				parent = parents.get(parent);
			}
			return newOpened;
		} else {
			opened.delete(id);
			return opened;
		}
	},
	select: () => null
};
var multipleOpenStrategy = {
	open: (_ref2) => {
		let { id, value, opened, parents } = _ref2;
		if (value) {
			let parent = parents.get(id);
			opened.add(id);
			while (parent != null && parent !== id) {
				opened.add(parent);
				parent = parents.get(parent);
			}
			return opened;
		} else opened.delete(id);
		return opened;
	},
	select: () => null
};
var listOpenStrategy = {
	open: multipleOpenStrategy.open,
	select: (_ref3) => {
		let { id, value, opened, parents } = _ref3;
		if (!value) return opened;
		const path = [];
		let parent = parents.get(id);
		while (parent != null) {
			path.push(parent);
			parent = parents.get(parent);
		}
		return new Set(path);
	}
};
//#endregion
//#region node_modules/vuetify/lib/composables/nested/selectStrategies.js
var independentSelectStrategy = (mandatory) => {
	const strategy = {
		select: (_ref) => {
			let { id, value, selected } = _ref;
			id = toRaw(id);
			if (mandatory && !value) {
				const on = Array.from(selected.entries()).reduce((arr, _ref2) => {
					let [key, value] = _ref2;
					if (value === "on") arr.push(key);
					return arr;
				}, []);
				if (on.length === 1 && on[0] === id) return selected;
			}
			selected.set(id, value ? "on" : "off");
			return selected;
		},
		in: (v, children, parents) => {
			const map = /* @__PURE__ */ new Map();
			for (const id of v || []) strategy.select({
				id,
				value: true,
				selected: map,
				children,
				parents
			});
			return map;
		},
		out: (v) => {
			const arr = [];
			for (const [key, value] of v.entries()) if (value === "on") arr.push(key);
			return arr;
		}
	};
	return strategy;
};
var independentSingleSelectStrategy = (mandatory) => {
	const parentStrategy = independentSelectStrategy(mandatory);
	return {
		select: (_ref3) => {
			let { selected, id, ...rest } = _ref3;
			id = toRaw(id);
			const singleSelected = selected.has(id) ? new Map([[id, selected.get(id)]]) : /* @__PURE__ */ new Map();
			return parentStrategy.select({
				...rest,
				id,
				selected: singleSelected
			});
		},
		in: (v, children, parents) => {
			if (v?.length) return parentStrategy.in(v.slice(0, 1), children, parents);
			return /* @__PURE__ */ new Map();
		},
		out: (v, children, parents) => {
			return parentStrategy.out(v, children, parents);
		}
	};
};
var leafSelectStrategy = (mandatory) => {
	const parentStrategy = independentSelectStrategy(mandatory);
	return {
		select: (_ref4) => {
			let { id, selected, children, ...rest } = _ref4;
			id = toRaw(id);
			if (children.has(id)) return selected;
			return parentStrategy.select({
				id,
				selected,
				children,
				...rest
			});
		},
		in: parentStrategy.in,
		out: parentStrategy.out
	};
};
var leafSingleSelectStrategy = (mandatory) => {
	const parentStrategy = independentSingleSelectStrategy(mandatory);
	return {
		select: (_ref5) => {
			let { id, selected, children, ...rest } = _ref5;
			id = toRaw(id);
			if (children.has(id)) return selected;
			return parentStrategy.select({
				id,
				selected,
				children,
				...rest
			});
		},
		in: parentStrategy.in,
		out: parentStrategy.out
	};
};
var classicSelectStrategy = (mandatory) => {
	const strategy = {
		select: (_ref6) => {
			let { id, value, selected, children, parents } = _ref6;
			id = toRaw(id);
			const original = new Map(selected);
			const items = [id];
			while (items.length) {
				const item = items.shift();
				selected.set(toRaw(item), value ? "on" : "off");
				if (children.has(item)) items.push(...children.get(item));
			}
			let parent = toRaw(parents.get(id));
			while (parent) {
				const childrenIds = children.get(parent);
				const everySelected = childrenIds.every((cid) => selected.get(toRaw(cid)) === "on");
				const noneSelected = childrenIds.every((cid) => !selected.has(toRaw(cid)) || selected.get(toRaw(cid)) === "off");
				selected.set(parent, everySelected ? "on" : noneSelected ? "off" : "indeterminate");
				parent = toRaw(parents.get(parent));
			}
			if (mandatory && !value) {
				if (Array.from(selected.entries()).reduce((arr, _ref7) => {
					let [key, value] = _ref7;
					if (value === "on") arr.push(key);
					return arr;
				}, []).length === 0) return original;
			}
			return selected;
		},
		in: (v, children, parents) => {
			let map = /* @__PURE__ */ new Map();
			for (const id of v || []) map = strategy.select({
				id,
				value: true,
				selected: map,
				children,
				parents
			});
			return map;
		},
		out: (v, children) => {
			const arr = [];
			for (const [key, value] of v.entries()) if (value === "on" && !children.has(key)) arr.push(key);
			return arr;
		}
	};
	return strategy;
};
var trunkSelectStrategy = (mandatory) => {
	const parentStrategy = classicSelectStrategy(mandatory);
	return {
		select: parentStrategy.select,
		in: parentStrategy.in,
		out: (v, children, parents) => {
			const arr = [];
			for (const [key, value] of v.entries()) if (value === "on") {
				if (parents.has(key)) {
					const parent = parents.get(key);
					if (v.get(parent) === "on") continue;
				}
				arr.push(key);
			}
			return arr;
		}
	};
};
//#endregion
//#region node_modules/vuetify/lib/composables/nested/nested.js
var VNestedSymbol = Symbol.for("vuetify:nested");
var emptyNested = {
	id: shallowRef(),
	root: {
		register: () => null,
		unregister: () => null,
		parents: ref(/* @__PURE__ */ new Map()),
		children: ref(/* @__PURE__ */ new Map()),
		open: () => null,
		openOnSelect: () => null,
		activate: () => null,
		select: () => null,
		activatable: ref(false),
		selectable: ref(false),
		opened: ref(/* @__PURE__ */ new Set()),
		activated: ref(/* @__PURE__ */ new Set()),
		selected: ref(/* @__PURE__ */ new Map()),
		selectedValues: ref([]),
		getPath: () => []
	}
};
var makeNestedProps = propsFactory({
	activatable: Boolean,
	selectable: Boolean,
	activeStrategy: [
		String,
		Function,
		Object
	],
	selectStrategy: [
		String,
		Function,
		Object
	],
	openStrategy: [String, Object],
	opened: null,
	activated: null,
	selected: null,
	mandatory: Boolean
}, "nested");
var useNested = (props) => {
	let isUnmounted = false;
	const children = ref(/* @__PURE__ */ new Map());
	const parents = ref(/* @__PURE__ */ new Map());
	const opened = useProxiedModel(props, "opened", props.opened, (v) => new Set(v), (v) => [...v.values()]);
	const activeStrategy = computed(() => {
		if (typeof props.activeStrategy === "object") return props.activeStrategy;
		if (typeof props.activeStrategy === "function") return props.activeStrategy(props.mandatory);
		switch (props.activeStrategy) {
			case "leaf": return leafActiveStrategy(props.mandatory);
			case "single-leaf": return leafSingleActiveStrategy(props.mandatory);
			case "independent": return independentActiveStrategy(props.mandatory);
			default: return independentSingleActiveStrategy(props.mandatory);
		}
	});
	const selectStrategy = computed(() => {
		if (typeof props.selectStrategy === "object") return props.selectStrategy;
		if (typeof props.selectStrategy === "function") return props.selectStrategy(props.mandatory);
		switch (props.selectStrategy) {
			case "single-leaf": return leafSingleSelectStrategy(props.mandatory);
			case "leaf": return leafSelectStrategy(props.mandatory);
			case "independent": return independentSelectStrategy(props.mandatory);
			case "single-independent": return independentSingleSelectStrategy(props.mandatory);
			case "trunk": return trunkSelectStrategy(props.mandatory);
			default: return classicSelectStrategy(props.mandatory);
		}
	});
	const openStrategy = computed(() => {
		if (typeof props.openStrategy === "object") return props.openStrategy;
		switch (props.openStrategy) {
			case "list": return listOpenStrategy;
			case "single": return singleOpenStrategy;
			default: return multipleOpenStrategy;
		}
	});
	const activated = useProxiedModel(props, "activated", props.activated, (v) => activeStrategy.value.in(v, children.value, parents.value), (v) => activeStrategy.value.out(v, children.value, parents.value));
	const selected = useProxiedModel(props, "selected", props.selected, (v) => selectStrategy.value.in(v, children.value, parents.value), (v) => selectStrategy.value.out(v, children.value, parents.value));
	onBeforeUnmount(() => {
		isUnmounted = true;
	});
	function getPath(id) {
		const path = [];
		let parent = id;
		while (parent != null) {
			path.unshift(parent);
			parent = parents.value.get(parent);
		}
		return path;
	}
	const vm = getCurrentInstance$1("nested");
	const nodeIds = /* @__PURE__ */ new Set();
	const nested = {
		id: shallowRef(),
		root: {
			opened,
			activatable: toRef(() => props.activatable),
			selectable: toRef(() => props.selectable),
			activated,
			selected,
			selectedValues: computed(() => {
				const arr = [];
				for (const [key, value] of selected.value.entries()) if (value === "on") arr.push(key);
				return arr;
			}),
			register: (id, parentId, isGroup) => {
				if (nodeIds.has(id)) {
					consoleError(`Multiple nodes with the same ID\n\t${getPath(id).map(String).join(" -> ")}\n\t${getPath(parentId).concat(id).map(String).join(" -> ")}`);
					return;
				} else nodeIds.add(id);
				parentId && id !== parentId && parents.value.set(id, parentId);
				isGroup && children.value.set(id, []);
				if (parentId != null) children.value.set(parentId, [...children.value.get(parentId) || [], id]);
			},
			unregister: (id) => {
				if (isUnmounted) return;
				nodeIds.delete(id);
				children.value.delete(id);
				const parent = parents.value.get(id);
				if (parent) {
					const list = children.value.get(parent) ?? [];
					children.value.set(parent, list.filter((child) => child !== id));
				}
				parents.value.delete(id);
			},
			open: (id, value, event) => {
				vm.emit("click:open", {
					id,
					value,
					path: getPath(id),
					event
				});
				const newOpened = openStrategy.value.open({
					id,
					value,
					opened: new Set(opened.value),
					children: children.value,
					parents: parents.value,
					event
				});
				newOpened && (opened.value = newOpened);
			},
			openOnSelect: (id, value, event) => {
				const newOpened = openStrategy.value.select({
					id,
					value,
					selected: new Map(selected.value),
					opened: new Set(opened.value),
					children: children.value,
					parents: parents.value,
					event
				});
				newOpened && (opened.value = newOpened);
			},
			select: (id, value, event) => {
				vm.emit("click:select", {
					id,
					value,
					path: getPath(id),
					event
				});
				const newSelected = selectStrategy.value.select({
					id,
					value,
					selected: new Map(selected.value),
					children: children.value,
					parents: parents.value,
					event
				});
				newSelected && (selected.value = newSelected);
				nested.root.openOnSelect(id, value, event);
			},
			activate: (id, value, event) => {
				if (!props.activatable) return nested.root.select(id, true, event);
				vm.emit("click:activate", {
					id,
					value,
					path: getPath(id),
					event
				});
				const newActivated = activeStrategy.value.activate({
					id,
					value,
					activated: new Set(activated.value),
					children: children.value,
					parents: parents.value,
					event
				});
				if (newActivated.size !== activated.value.size) activated.value = newActivated;
				else {
					for (const value of newActivated) if (!activated.value.has(value)) {
						activated.value = newActivated;
						return;
					}
					for (const value of activated.value) if (!newActivated.has(value)) {
						activated.value = newActivated;
						return;
					}
				}
			},
			children,
			parents,
			getPath
		}
	};
	provide(VNestedSymbol, nested);
	return nested.root;
};
var useNestedItem = (id, isGroup) => {
	const parent = inject(VNestedSymbol, emptyNested);
	const uidSymbol = Symbol("nested item");
	const computedId = computed(() => toValue(id) ?? uidSymbol);
	const item = {
		...parent,
		id: computedId,
		open: (open, e) => parent.root.open(computedId.value, open, e),
		openOnSelect: (open, e) => parent.root.openOnSelect(computedId.value, open, e),
		isOpen: computed(() => parent.root.opened.value.has(computedId.value)),
		parent: computed(() => parent.root.parents.value.get(computedId.value)),
		activate: (activated, e) => parent.root.activate(computedId.value, activated, e),
		isActivated: computed(() => parent.root.activated.value.has(toRaw(computedId.value))),
		select: (selected, e) => parent.root.select(computedId.value, selected, e),
		isSelected: computed(() => parent.root.selected.value.get(toRaw(computedId.value)) === "on"),
		isIndeterminate: computed(() => parent.root.selected.value.get(toRaw(computedId.value)) === "indeterminate"),
		isLeaf: computed(() => !parent.root.children.value.get(computedId.value)),
		isGroupActivator: parent.isGroupActivator
	};
	onBeforeMount(() => {
		!parent.isGroupActivator && parent.root.register(computedId.value, parent.id.value, isGroup);
	});
	onBeforeUnmount(() => {
		!parent.isGroupActivator && parent.root.unregister(computedId.value);
	});
	isGroup && provide(VNestedSymbol, item);
	return item;
};
var useNestedGroupActivator = () => {
	provide(VNestedSymbol, {
		...inject(VNestedSymbol, emptyNested),
		isGroupActivator: true
	});
};
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListGroup.js
var VListGroupActivator = defineComponent$1({
	name: "VListGroupActivator",
	setup(_, _ref) {
		let { slots } = _ref;
		useNestedGroupActivator();
		return () => slots.default?.();
	}
});
var makeVListGroupProps = propsFactory({
	activeColor: String,
	baseColor: String,
	color: String,
	collapseIcon: {
		type: IconValue,
		default: "$collapse"
	},
	expandIcon: {
		type: IconValue,
		default: "$expand"
	},
	prependIcon: IconValue,
	appendIcon: IconValue,
	fluid: Boolean,
	subgroup: Boolean,
	title: String,
	value: null,
	...makeComponentProps(),
	...makeTagProps()
}, "VListGroup");
var VListGroup = genericComponent()({
	name: "VListGroup",
	props: makeVListGroupProps(),
	setup(props, _ref2) {
		let { slots } = _ref2;
		const { isOpen, open, id: _id } = useNestedItem(() => props.value, true);
		const id = computed(() => `v-list-group--id-${String(_id.value)}`);
		const list = useList();
		const { isBooted } = useSsrBoot();
		function onClick(e) {
			e.stopPropagation();
			if (["INPUT", "TEXTAREA"].includes(e.target?.tagName)) return;
			open(!isOpen.value, e);
		}
		const activatorProps = computed(() => ({
			onClick,
			class: "v-list-group__header",
			id: id.value
		}));
		const toggleIcon = computed(() => isOpen.value ? props.collapseIcon : props.expandIcon);
		const activatorDefaults = computed(() => ({ VListItem: {
			active: isOpen.value,
			activeColor: props.activeColor,
			baseColor: props.baseColor,
			color: props.color,
			prependIcon: props.prependIcon || props.subgroup && toggleIcon.value,
			appendIcon: props.appendIcon || !props.subgroup && toggleIcon.value,
			title: props.title,
			value: props.value
		} }));
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-list-group",
				{
					"v-list-group--prepend": list?.hasPrepend.value,
					"v-list-group--fluid": props.fluid,
					"v-list-group--subgroup": props.subgroup,
					"v-list-group--open": isOpen.value
				},
				props.class
			],
			"style": props.style
		}, { default: () => [slots.activator && createVNode(VDefaultsProvider, { "defaults": activatorDefaults.value }, { default: () => [createVNode(VListGroupActivator, null, { default: () => [slots.activator({
			props: activatorProps.value,
			isOpen: isOpen.value
		})] })] }), createVNode(MaybeTransition, {
			"transition": { component: VExpandTransition },
			"disabled": !isBooted.value
		}, { default: () => [withDirectives(createVNode("div", {
			"class": "v-list-group__items",
			"role": "group",
			"aria-labelledby": id.value
		}, [slots.default?.()]), [[vShow, isOpen.value]])] })] }));
		return { isOpen };
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListItemSubtitle.js
var makeVListItemSubtitleProps = propsFactory({
	opacity: [Number, String],
	...makeComponentProps(),
	...makeTagProps()
}, "VListItemSubtitle");
var VListItemSubtitle = genericComponent()({
	name: "VListItemSubtitle",
	props: makeVListItemSubtitleProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode(props.tag, {
			"class": ["v-list-item-subtitle", props.class],
			"style": [{ "--v-list-item-subtitle-opacity": props.opacity }, props.style]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListItemTitle.js
var VListItemTitle = createSimpleFunctional("v-list-item-title");
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListItem.js
var makeVListItemProps = propsFactory({
	active: {
		type: Boolean,
		default: void 0
	},
	activeClass: String,
	activeColor: String,
	appendAvatar: String,
	appendIcon: IconValue,
	baseColor: String,
	disabled: Boolean,
	lines: [Boolean, String],
	link: {
		type: Boolean,
		default: void 0
	},
	nav: Boolean,
	prependAvatar: String,
	prependIcon: IconValue,
	ripple: {
		type: [Boolean, Object],
		default: true
	},
	slim: Boolean,
	subtitle: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	title: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	value: null,
	onClick: EventProp(),
	onClickOnce: EventProp(),
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeDimensionProps(),
	...makeElevationProps(),
	...makeRoundedProps(),
	...makeRouterProps(),
	...makeTagProps(),
	...makeThemeProps(),
	...makeVariantProps({ variant: "text" })
}, "VListItem");
var VListItem = genericComponent()({
	name: "VListItem",
	directives: { Ripple },
	props: makeVListItemProps(),
	emits: { click: (e) => true },
	setup(props, _ref) {
		let { attrs, slots, emit } = _ref;
		const link = useLink(props, attrs);
		const { activate, isActivated, select, isOpen, isSelected, isIndeterminate, isGroupActivator, root, parent, openOnSelect, id: uid } = useNestedItem(computed(() => props.value === void 0 ? link.href.value : props.value), false);
		const list = useList();
		const isActive = computed(() => props.active !== false && (props.active || link.isActive?.value || (root.activatable.value ? isActivated.value : isSelected.value)));
		const isLink = toRef(() => props.link !== false && link.isLink.value);
		const isSelectable = computed(() => !!list && (root.selectable.value || root.activatable.value || props.value != null));
		const isClickable = computed(() => !props.disabled && props.link !== false && (props.link || link.isClickable.value || isSelectable.value));
		const roundedProps = toRef(() => props.rounded || props.nav);
		const color = toRef(() => props.color ?? props.activeColor);
		const variantProps = toRef(() => ({
			color: isActive.value ? color.value ?? props.baseColor : props.baseColor,
			variant: props.variant
		}));
		watch(() => link.isActive?.value, (val) => {
			if (!val) return;
			handleActiveLink();
		});
		onBeforeMount(() => {
			if (link.isActive?.value) handleActiveLink();
		});
		function handleActiveLink() {
			if (parent.value != null) root.open(parent.value, true);
			openOnSelect(true);
		}
		const { themeClasses } = provideTheme(props);
		const { borderClasses } = useBorder(props);
		const { colorClasses, colorStyles, variantClasses } = useVariant(variantProps);
		const { densityClasses } = useDensity(props);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(roundedProps);
		const lineClasses = toRef(() => props.lines ? `v-list-item--${props.lines}-line` : void 0);
		const slotProps = computed(() => ({
			isActive: isActive.value,
			select,
			isOpen: isOpen.value,
			isSelected: isSelected.value,
			isIndeterminate: isIndeterminate.value
		}));
		function onClick(e) {
			emit("click", e);
			if (["INPUT", "TEXTAREA"].includes(e.target?.tagName)) return;
			if (!isClickable.value) return;
			link.navigate?.(e);
			if (isGroupActivator) return;
			if (root.activatable.value) activate(!isActivated.value, e);
			else if (root.selectable.value) select(!isSelected.value, e);
			else if (props.value != null) select(!isSelected.value, e);
		}
		function onKeyDown(e) {
			const target = e.target;
			if (["INPUT", "TEXTAREA"].includes(target.tagName)) return;
			if (e.key === "Enter" || e.key === " ") {
				e.preventDefault();
				e.target.dispatchEvent(new MouseEvent("click", e));
			}
		}
		useRender(() => {
			const Tag = isLink.value ? "a" : props.tag;
			const hasTitle = slots.title || props.title != null;
			const hasSubtitle = slots.subtitle || props.subtitle != null;
			const hasAppendMedia = !!(props.appendAvatar || props.appendIcon);
			const hasAppend = !!(hasAppendMedia || slots.append);
			const hasPrependMedia = !!(props.prependAvatar || props.prependIcon);
			const hasPrepend = !!(hasPrependMedia || slots.prepend);
			list?.updateHasPrepend(hasPrepend);
			if (props.activeColor) deprecate("active-color", ["color", "base-color"]);
			return withDirectives(createVNode(Tag, mergeProps({
				"class": [
					"v-list-item",
					{
						"v-list-item--active": isActive.value,
						"v-list-item--disabled": props.disabled,
						"v-list-item--link": isClickable.value,
						"v-list-item--nav": props.nav,
						"v-list-item--prepend": !hasPrepend && list?.hasPrepend.value,
						"v-list-item--slim": props.slim,
						[`${props.activeClass}`]: props.activeClass && isActive.value
					},
					themeClasses.value,
					borderClasses.value,
					colorClasses.value,
					densityClasses.value,
					elevationClasses.value,
					lineClasses.value,
					roundedClasses.value,
					variantClasses.value,
					props.class
				],
				"style": [
					colorStyles.value,
					dimensionStyles.value,
					props.style
				],
				"tabindex": isClickable.value ? list ? -2 : 0 : void 0,
				"aria-selected": isSelectable.value ? root.activatable.value ? isActivated.value : root.selectable.value ? isSelected.value : isActive.value : void 0,
				"onClick": onClick,
				"onKeydown": isClickable.value && !isLink.value && onKeyDown
			}, link.linkProps), { default: () => [
				genOverlays(isClickable.value || isActive.value, "v-list-item"),
				hasPrepend && createVNode("div", {
					"key": "prepend",
					"class": "v-list-item__prepend"
				}, [!slots.prepend ? createVNode(Fragment, null, [props.prependAvatar && createVNode(VAvatar, {
					"key": "prepend-avatar",
					"density": props.density,
					"image": props.prependAvatar
				}, null), props.prependIcon && createVNode(VIcon, {
					"key": "prepend-icon",
					"density": props.density,
					"icon": props.prependIcon
				}, null)]) : createVNode(VDefaultsProvider, {
					"key": "prepend-defaults",
					"disabled": !hasPrependMedia,
					"defaults": {
						VAvatar: {
							density: props.density,
							image: props.prependAvatar
						},
						VIcon: {
							density: props.density,
							icon: props.prependIcon
						},
						VListItemAction: { start: true }
					}
				}, { default: () => [slots.prepend?.(slotProps.value)] }), createVNode("div", { "class": "v-list-item__spacer" }, null)]),
				createVNode("div", {
					"class": "v-list-item__content",
					"data-no-activator": ""
				}, [
					hasTitle && createVNode(VListItemTitle, { "key": "title" }, { default: () => [slots.title?.({ title: props.title }) ?? toDisplayString(props.title)] }),
					hasSubtitle && createVNode(VListItemSubtitle, { "key": "subtitle" }, { default: () => [slots.subtitle?.({ subtitle: props.subtitle }) ?? toDisplayString(props.subtitle)] }),
					slots.default?.(slotProps.value)
				]),
				hasAppend && createVNode("div", {
					"key": "append",
					"class": "v-list-item__append"
				}, [!slots.append ? createVNode(Fragment, null, [props.appendIcon && createVNode(VIcon, {
					"key": "append-icon",
					"density": props.density,
					"icon": props.appendIcon
				}, null), props.appendAvatar && createVNode(VAvatar, {
					"key": "append-avatar",
					"density": props.density,
					"image": props.appendAvatar
				}, null)]) : createVNode(VDefaultsProvider, {
					"key": "append-defaults",
					"disabled": !hasAppendMedia,
					"defaults": {
						VAvatar: {
							density: props.density,
							image: props.appendAvatar
						},
						VIcon: {
							density: props.density,
							icon: props.appendIcon
						},
						VListItemAction: { end: true }
					}
				}, { default: () => [slots.append?.(slotProps.value)] }), createVNode("div", { "class": "v-list-item__spacer" }, null)])
			] }), [[resolveDirective("ripple"), isClickable.value && props.ripple]]);
		});
		return {
			activate,
			isActivated,
			isGroupActivator,
			isSelected,
			list,
			select,
			root,
			id: uid,
			link
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListSubheader.js
var makeVListSubheaderProps = propsFactory({
	color: String,
	inset: Boolean,
	sticky: Boolean,
	title: String,
	...makeComponentProps(),
	...makeTagProps()
}, "VListSubheader");
var VListSubheader = genericComponent()({
	name: "VListSubheader",
	props: makeVListSubheaderProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { textColorClasses, textColorStyles } = useTextColor(() => props.color);
		useRender(() => {
			const hasText = !!(slots.default || props.title);
			return createVNode(props.tag, {
				"class": [
					"v-list-subheader",
					{
						"v-list-subheader--inset": props.inset,
						"v-list-subheader--sticky": props.sticky
					},
					textColorClasses.value,
					props.class
				],
				"style": [{ textColorStyles }, props.style]
			}, { default: () => [hasText && createVNode("div", { "class": "v-list-subheader__text" }, [slots.default?.() ?? props.title])] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDivider/VDivider.js
var makeVDividerProps = propsFactory({
	color: String,
	inset: Boolean,
	length: [Number, String],
	opacity: [Number, String],
	thickness: [Number, String],
	vertical: Boolean,
	...makeComponentProps(),
	...makeThemeProps()
}, "VDivider");
var VDivider = genericComponent()({
	name: "VDivider",
	props: makeVDividerProps(),
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { textColorClasses, textColorStyles } = useTextColor(() => props.color);
		const dividerStyles = computed(() => {
			const styles = {};
			if (props.length) styles[props.vertical ? "height" : "width"] = convertToUnit(props.length);
			if (props.thickness) styles[props.vertical ? "borderRightWidth" : "borderTopWidth"] = convertToUnit(props.thickness);
			return styles;
		});
		useRender(() => {
			const divider = createVNode("hr", {
				"class": [
					{
						"v-divider": true,
						"v-divider--inset": props.inset,
						"v-divider--vertical": props.vertical
					},
					themeClasses.value,
					textColorClasses.value,
					props.class
				],
				"style": [
					dividerStyles.value,
					textColorStyles.value,
					{ "--v-border-opacity": props.opacity },
					props.style
				],
				"aria-orientation": !attrs.role || attrs.role === "separator" ? props.vertical ? "vertical" : "horizontal" : void 0,
				"role": `${attrs.role || "separator"}`
			}, null);
			if (!slots.default) return divider;
			return createVNode("div", { "class": ["v-divider__wrapper", {
				"v-divider__wrapper--vertical": props.vertical,
				"v-divider__wrapper--inset": props.inset
			}] }, [
				divider,
				createVNode("div", { "class": "v-divider__content" }, [slots.default()]),
				divider
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListChildren.js
var makeVListChildrenProps = propsFactory({
	items: Array,
	returnObject: Boolean
}, "VListChildren");
var VListChildren = genericComponent()({
	name: "VListChildren",
	props: makeVListChildrenProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		createList();
		return () => slots.default?.() ?? props.items?.map((_ref2) => {
			let { children, props: itemProps, type, raw: item } = _ref2;
			if (type === "divider") return slots.divider?.({ props: itemProps }) ?? createVNode(VDivider, itemProps, null);
			if (type === "subheader") return slots.subheader?.({ props: itemProps }) ?? createVNode(VListSubheader, itemProps, null);
			const slotsWithItem = {
				subtitle: slots.subtitle ? (slotProps) => slots.subtitle?.({
					...slotProps,
					item
				}) : void 0,
				prepend: slots.prepend ? (slotProps) => slots.prepend?.({
					...slotProps,
					item
				}) : void 0,
				append: slots.append ? (slotProps) => slots.append?.({
					...slotProps,
					item
				}) : void 0,
				title: slots.title ? (slotProps) => slots.title?.({
					...slotProps,
					item
				}) : void 0
			};
			const listGroupProps = VListGroup.filterProps(itemProps);
			return children ? createVNode(VListGroup, mergeProps({ "value": itemProps?.value }, listGroupProps), {
				activator: (_ref3) => {
					let { props: activatorProps } = _ref3;
					const listItemProps = {
						...itemProps,
						...activatorProps,
						value: props.returnObject ? item : itemProps.value
					};
					return slots.header ? slots.header({ props: listItemProps }) : createVNode(VListItem, listItemProps, slotsWithItem);
				},
				default: () => createVNode(VListChildren, {
					"items": children,
					"returnObject": props.returnObject
				}, slots)
			}) : slots.item ? slots.item({ props: itemProps }) : createVNode(VListItem, mergeProps(itemProps, { "value": props.returnObject ? item : itemProps.value }), slotsWithItem);
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/list-items.js
var makeItemsProps = propsFactory({
	items: {
		type: Array,
		default: () => []
	},
	itemTitle: {
		type: [
			String,
			Array,
			Function
		],
		default: "title"
	},
	itemValue: {
		type: [
			String,
			Array,
			Function
		],
		default: "value"
	},
	itemChildren: {
		type: [
			Boolean,
			String,
			Array,
			Function
		],
		default: "children"
	},
	itemProps: {
		type: [
			Boolean,
			String,
			Array,
			Function
		],
		default: "props"
	},
	returnObject: Boolean,
	valueComparator: Function
}, "list-items");
function transformItem$3(props, item) {
	const title = getPropertyFromItem(item, props.itemTitle, item);
	const value = getPropertyFromItem(item, props.itemValue, title);
	const children = getPropertyFromItem(item, props.itemChildren);
	const _props = {
		title,
		value,
		...props.itemProps === true ? typeof item === "object" && item != null && !Array.isArray(item) ? "children" in item ? omit(item, ["children"]) : item : void 0 : getPropertyFromItem(item, props.itemProps)
	};
	return {
		title: String(_props.title ?? ""),
		value: _props.value,
		props: _props,
		children: Array.isArray(children) ? transformItems$3(props, children) : void 0,
		raw: item
	};
}
function transformItems$3(props, items) {
	const _props = pick(props, [
		"itemTitle",
		"itemValue",
		"itemChildren",
		"itemProps",
		"returnObject",
		"valueComparator"
	]);
	const array = [];
	for (const item of items) array.push(transformItem$3(_props, item));
	return array;
}
function useItems(props) {
	const items = computed(() => transformItems$3(props, props.items));
	const hasNullItem = computed(() => items.value.some((item) => item.value === null));
	const itemsMap = shallowRef(/* @__PURE__ */ new Map());
	const keylessItems = shallowRef([]);
	watchEffect(() => {
		const _items = items.value;
		const map = /* @__PURE__ */ new Map();
		const keyless = [];
		for (let i = 0; i < _items.length; i++) {
			const item = _items[i];
			if (isPrimitive(item.value) || item.value === null) {
				let values = map.get(item.value);
				if (!values) {
					values = [];
					map.set(item.value, values);
				}
				values.push(item);
			} else keyless.push(item);
		}
		itemsMap.value = map;
		keylessItems.value = keyless;
	});
	function transformIn(value) {
		const _items = itemsMap.value;
		const _allItems = items.value;
		const _keylessItems = keylessItems.value;
		const _hasNullItem = hasNullItem.value;
		const _returnObject = props.returnObject;
		const hasValueComparator = !!props.valueComparator;
		const valueComparator = props.valueComparator || deepEqual;
		const _props = pick(props, [
			"itemTitle",
			"itemValue",
			"itemChildren",
			"itemProps",
			"returnObject",
			"valueComparator"
		]);
		const returnValue = [];
		main: for (const v of value) {
			if (!_hasNullItem && v === null) continue;
			if (_returnObject && typeof v === "string") {
				returnValue.push(transformItem$3(_props, v));
				continue;
			}
			const fastItems = _items.get(v);
			if (hasValueComparator || !fastItems) {
				for (const item of hasValueComparator ? _allItems : _keylessItems) if (valueComparator(v, item.value)) {
					returnValue.push(item);
					continue main;
				}
				returnValue.push(transformItem$3(_props, v));
				continue;
			}
			returnValue.push(...fastItems);
		}
		return returnValue;
	}
	function transformOut(value) {
		return props.returnObject ? value.map((_ref) => {
			let { raw } = _ref;
			return raw;
		}) : value.map((_ref2) => {
			let { value } = _ref2;
			return value;
		});
	}
	return {
		items,
		transformIn,
		transformOut
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VList/VList.js
function transformItem$2(props, item) {
	const type = getPropertyFromItem(item, props.itemType, "item");
	const title = isPrimitive(item) ? item : getPropertyFromItem(item, props.itemTitle);
	const value = getPropertyFromItem(item, props.itemValue, void 0);
	const children = getPropertyFromItem(item, props.itemChildren);
	const _props = {
		title,
		value,
		...props.itemProps === true ? omit(item, ["children"]) : getPropertyFromItem(item, props.itemProps)
	};
	return {
		type,
		title: _props.title,
		value: _props.value,
		props: _props,
		children: type === "item" && children ? transformItems$2(props, children) : void 0,
		raw: item
	};
}
function transformItems$2(props, items) {
	const array = [];
	for (const item of items) array.push(transformItem$2(props, item));
	return array;
}
function useListItems(props) {
	return { items: computed(() => transformItems$2(props, props.items)) };
}
var makeVListProps = propsFactory({
	baseColor: String,
	activeColor: String,
	activeClass: String,
	bgColor: String,
	disabled: Boolean,
	expandIcon: IconValue,
	collapseIcon: IconValue,
	lines: {
		type: [Boolean, String],
		default: "one"
	},
	slim: Boolean,
	nav: Boolean,
	"onClick:open": EventProp(),
	"onClick:select": EventProp(),
	"onUpdate:opened": EventProp(),
	...makeNestedProps({
		selectStrategy: "single-leaf",
		openStrategy: "list"
	}),
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeDimensionProps(),
	...makeElevationProps(),
	itemType: {
		type: String,
		default: "type"
	},
	...makeItemsProps(),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps(),
	...makeVariantProps({ variant: "text" })
}, "VList");
var VList = genericComponent()({
	name: "VList",
	props: makeVListProps(),
	emits: {
		"update:selected": (value) => true,
		"update:activated": (value) => true,
		"update:opened": (value) => true,
		"click:open": (value) => true,
		"click:activate": (value) => true,
		"click:select": (value) => true
	},
	setup(props, _ref) {
		let { slots } = _ref;
		const { items } = useListItems(props);
		const { themeClasses } = provideTheme(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { borderClasses } = useBorder(props);
		const { densityClasses } = useDensity(props);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		const { children, open, parents, select, getPath } = useNested(props);
		const lineClasses = toRef(() => props.lines ? `v-list--${props.lines}-line` : void 0);
		const activeColor = toRef(() => props.activeColor);
		const baseColor = toRef(() => props.baseColor);
		const color = toRef(() => props.color);
		createList();
		provideDefaults({
			VListGroup: {
				activeColor,
				baseColor,
				color,
				expandIcon: toRef(() => props.expandIcon),
				collapseIcon: toRef(() => props.collapseIcon)
			},
			VListItem: {
				activeClass: toRef(() => props.activeClass),
				activeColor,
				baseColor,
				color,
				density: toRef(() => props.density),
				disabled: toRef(() => props.disabled),
				lines: toRef(() => props.lines),
				nav: toRef(() => props.nav),
				slim: toRef(() => props.slim),
				variant: toRef(() => props.variant)
			}
		});
		const isFocused = shallowRef(false);
		const contentRef = ref();
		function onFocusin(e) {
			isFocused.value = true;
		}
		function onFocusout(e) {
			isFocused.value = false;
		}
		function onFocus(e) {
			if (!isFocused.value && !(e.relatedTarget && contentRef.value?.contains(e.relatedTarget))) focus();
		}
		function onKeydown(e) {
			const target = e.target;
			if (!contentRef.value || ["INPUT", "TEXTAREA"].includes(target.tagName)) return;
			if (e.key === "ArrowDown") focus("next");
			else if (e.key === "ArrowUp") focus("prev");
			else if (e.key === "Home") focus("first");
			else if (e.key === "End") focus("last");
			else return;
			e.preventDefault();
		}
		function onMousedown(e) {
			isFocused.value = true;
		}
		function focus(location) {
			if (contentRef.value) return focusChild(contentRef.value, location);
		}
		useRender(() => {
			return createVNode(props.tag, {
				"ref": contentRef,
				"class": [
					"v-list",
					{
						"v-list--disabled": props.disabled,
						"v-list--nav": props.nav,
						"v-list--slim": props.slim
					},
					themeClasses.value,
					backgroundColorClasses.value,
					borderClasses.value,
					densityClasses.value,
					elevationClasses.value,
					lineClasses.value,
					roundedClasses.value,
					props.class
				],
				"style": [
					backgroundColorStyles.value,
					dimensionStyles.value,
					props.style
				],
				"tabindex": props.disabled ? -1 : 0,
				"role": "listbox",
				"aria-activedescendant": void 0,
				"onFocusin": onFocusin,
				"onFocusout": onFocusout,
				"onFocus": onFocus,
				"onKeydown": onKeydown,
				"onMousedown": onMousedown
			}, { default: () => [createVNode(VListChildren, {
				"items": items.value,
				"returnObject": props.returnObject
			}, slots)] });
		});
		return {
			open,
			select,
			focus,
			children,
			parents,
			getPath
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListImg.js
var VListImg = createSimpleFunctional("v-list-img");
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListItemAction.js
var makeVListItemActionProps = propsFactory({
	start: Boolean,
	end: Boolean,
	...makeComponentProps(),
	...makeTagProps()
}, "VListItemAction");
var VListItemAction = genericComponent()({
	name: "VListItemAction",
	props: makeVListItemActionProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-list-item-action",
				{
					"v-list-item-action--start": props.start,
					"v-list-item-action--end": props.end
				},
				props.class
			],
			"style": props.style
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VList/VListItemMedia.js
var makeVListItemMediaProps = propsFactory({
	start: Boolean,
	end: Boolean,
	...makeComponentProps(),
	...makeTagProps()
}, "VListItemMedia");
var VListItemMedia = genericComponent()({
	name: "VListItemMedia",
	props: makeVListItemMediaProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => {
			return createVNode(props.tag, {
				"class": [
					"v-list-item-media",
					{
						"v-list-item-media--start": props.start,
						"v-list-item-media--end": props.end
					},
					props.class
				],
				"style": props.style
			}, slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VOverlay/util/point.js
/** Convert a point in local space to viewport space */
function elementToViewport(point, offset) {
	return {
		x: point.x + offset.x,
		y: point.y + offset.y
	};
}
/** Get the difference between two points */
function getOffset$1(a, b) {
	return {
		x: a.x - b.x,
		y: a.y - b.y
	};
}
/** Convert an anchor object to a point in local space */
function anchorToPoint(anchor, box) {
	if (anchor.side === "top" || anchor.side === "bottom") {
		const { side, align } = anchor;
		return elementToViewport({
			x: align === "left" ? 0 : align === "center" ? box.width / 2 : align === "right" ? box.width : align,
			y: side === "top" ? 0 : side === "bottom" ? box.height : side
		}, box);
	} else if (anchor.side === "left" || anchor.side === "right") {
		const { side, align } = anchor;
		return elementToViewport({
			x: side === "left" ? 0 : side === "right" ? box.width : side,
			y: align === "top" ? 0 : align === "center" ? box.height / 2 : align === "bottom" ? box.height : align
		}, box);
	}
	return elementToViewport({
		x: box.width / 2,
		y: box.height / 2
	}, box);
}
//#endregion
//#region node_modules/vuetify/lib/components/VOverlay/locationStrategies.js
var locationStrategies = {
	static: staticLocationStrategy,
	connected: connectedLocationStrategy
};
var makeLocationStrategyProps = propsFactory({
	locationStrategy: {
		type: [String, Function],
		default: "static",
		validator: (val) => typeof val === "function" || val in locationStrategies
	},
	location: {
		type: String,
		default: "bottom"
	},
	origin: {
		type: String,
		default: "auto"
	},
	offset: [
		Number,
		String,
		Array
	]
}, "VOverlay-location-strategies");
function useLocationStrategies(props, data) {
	const contentStyles = ref({});
	const updateLocation = ref();
	if (IN_BROWSER) useToggleScope(() => !!(data.isActive.value && props.locationStrategy), (reset) => {
		watch(() => props.locationStrategy, reset);
		onScopeDispose(() => {
			window.removeEventListener("resize", onResize);
			updateLocation.value = void 0;
		});
		window.addEventListener("resize", onResize, { passive: true });
		if (typeof props.locationStrategy === "function") updateLocation.value = props.locationStrategy(data, props, contentStyles)?.updateLocation;
		else updateLocation.value = locationStrategies[props.locationStrategy](data, props, contentStyles)?.updateLocation;
	});
	function onResize(e) {
		updateLocation.value?.(e);
	}
	return {
		contentStyles,
		updateLocation
	};
}
function staticLocationStrategy() {}
/** Get size of element ignoring max-width/max-height */
function getIntrinsicSize(el, isRtl) {
	const contentBox = nullifyTransforms(el);
	if (isRtl) contentBox.x += parseFloat(el.style.right || 0);
	else contentBox.x -= parseFloat(el.style.left || 0);
	contentBox.y -= parseFloat(el.style.top || 0);
	return contentBox;
}
function connectedLocationStrategy(data, props, contentStyles) {
	if (Array.isArray(data.target.value) || isFixedPosition(data.target.value)) Object.assign(contentStyles.value, {
		position: "fixed",
		top: 0,
		[data.isRtl.value ? "right" : "left"]: 0
	});
	const { preferredAnchor, preferredOrigin } = destructComputed(() => {
		const parsedAnchor = parseAnchor(props.location, data.isRtl.value);
		const parsedOrigin = props.origin === "overlap" ? parsedAnchor : props.origin === "auto" ? flipSide(parsedAnchor) : parseAnchor(props.origin, data.isRtl.value);
		if (parsedAnchor.side === parsedOrigin.side && parsedAnchor.align === flipAlign(parsedOrigin).align) return {
			preferredAnchor: flipCorner(parsedAnchor),
			preferredOrigin: flipCorner(parsedOrigin)
		};
		else return {
			preferredAnchor: parsedAnchor,
			preferredOrigin: parsedOrigin
		};
	});
	const [minWidth, minHeight, maxWidth, maxHeight] = [
		"minWidth",
		"minHeight",
		"maxWidth",
		"maxHeight"
	].map((key) => {
		return computed(() => {
			const val = parseFloat(props[key]);
			return isNaN(val) ? Infinity : val;
		});
	});
	const offset = computed(() => {
		if (Array.isArray(props.offset)) return props.offset;
		if (typeof props.offset === "string") {
			const offset = props.offset.split(" ").map(parseFloat);
			if (offset.length < 2) offset.push(0);
			return offset;
		}
		return typeof props.offset === "number" ? [props.offset, 0] : [0, 0];
	});
	let observe = false;
	let lastFrame = -1;
	const flipped = new CircularBuffer(4);
	const observer = new ResizeObserver(() => {
		if (!observe) return;
		requestAnimationFrame((newTime) => {
			if (newTime !== lastFrame) flipped.clear();
			requestAnimationFrame((newNewTime) => {
				lastFrame = newNewTime;
			});
		});
		if (flipped.isFull) {
			const values = flipped.values();
			if (deepEqual(values.at(-1), values.at(-3))) return;
		}
		const result = updateLocation();
		if (result) flipped.push(result.flipped);
	});
	watch([data.target, data.contentEl], (_ref, _ref2) => {
		let [newTarget, newContentEl] = _ref;
		let [oldTarget, oldContentEl] = _ref2;
		if (oldTarget && !Array.isArray(oldTarget)) observer.unobserve(oldTarget);
		if (newTarget && !Array.isArray(newTarget)) observer.observe(newTarget);
		if (oldContentEl) observer.unobserve(oldContentEl);
		if (newContentEl) observer.observe(newContentEl);
	}, { immediate: true });
	onScopeDispose(() => {
		observer.disconnect();
	});
	let targetBox = new Box({
		x: 0,
		y: 0,
		width: 0,
		height: 0
	});
	function updateLocation() {
		observe = false;
		requestAnimationFrame(() => observe = true);
		if (!data.target.value || !data.contentEl.value) return;
		if (Array.isArray(data.target.value) || data.target.value.offsetParent || data.target.value.getClientRects().length) targetBox = getTargetBox(data.target.value);
		const contentBox = getIntrinsicSize(data.contentEl.value, data.isRtl.value);
		const scrollParents = getScrollParents(data.contentEl.value);
		const viewportMargin = 12;
		if (!scrollParents.length) {
			scrollParents.push(document.documentElement);
			if (!(data.contentEl.value.style.top && data.contentEl.value.style.left)) {
				contentBox.x -= parseFloat(document.documentElement.style.getPropertyValue("--v-body-scroll-x") || 0);
				contentBox.y -= parseFloat(document.documentElement.style.getPropertyValue("--v-body-scroll-y") || 0);
			}
		}
		const viewport = scrollParents.reduce((box, el) => {
			const rect = el.getBoundingClientRect();
			const scrollBox = new Box({
				x: el === document.documentElement ? 0 : rect.x,
				y: el === document.documentElement ? 0 : rect.y,
				width: el.clientWidth,
				height: el.clientHeight
			});
			if (box) return new Box({
				x: Math.max(box.left, scrollBox.left),
				y: Math.max(box.top, scrollBox.top),
				width: Math.min(box.right, scrollBox.right) - Math.max(box.left, scrollBox.left),
				height: Math.min(box.bottom, scrollBox.bottom) - Math.max(box.top, scrollBox.top)
			});
			return scrollBox;
		}, void 0);
		viewport.x += viewportMargin;
		viewport.y += viewportMargin;
		viewport.width -= viewportMargin * 2;
		viewport.height -= viewportMargin * 2;
		let placement = {
			anchor: preferredAnchor.value,
			origin: preferredOrigin.value
		};
		function checkOverflow(_placement) {
			const box = new Box(contentBox);
			let { x, y } = getOffset$1(anchorToPoint(_placement.anchor, targetBox), anchorToPoint(_placement.origin, box));
			switch (_placement.anchor.side) {
				case "top":
					y -= offset.value[0];
					break;
				case "bottom":
					y += offset.value[0];
					break;
				case "left":
					x -= offset.value[0];
					break;
				case "right":
					x += offset.value[0];
					break;
			}
			switch (_placement.anchor.align) {
				case "top":
					y -= offset.value[1];
					break;
				case "bottom":
					y += offset.value[1];
					break;
				case "left":
					x -= offset.value[1];
					break;
				case "right":
					x += offset.value[1];
					break;
			}
			box.x += x;
			box.y += y;
			box.width = Math.min(box.width, maxWidth.value);
			box.height = Math.min(box.height, maxHeight.value);
			return {
				overflows: getOverflow(box, viewport),
				x,
				y
			};
		}
		let x = 0;
		let y = 0;
		const available = {
			x: 0,
			y: 0
		};
		const flipped = {
			x: false,
			y: false
		};
		let resets = -1;
		while (true) {
			if (resets++ > 10) {
				consoleError("Infinite loop detected in connectedLocationStrategy");
				break;
			}
			const { x: _x, y: _y, overflows } = checkOverflow(placement);
			x += _x;
			y += _y;
			contentBox.x += _x;
			contentBox.y += _y;
			{
				const axis = getAxis(placement.anchor);
				const hasOverflowX = overflows.x.before || overflows.x.after;
				const hasOverflowY = overflows.y.before || overflows.y.after;
				let reset = false;
				["x", "y"].forEach((key) => {
					if (key === "x" && hasOverflowX && !flipped.x || key === "y" && hasOverflowY && !flipped.y) {
						const newPlacement = {
							anchor: { ...placement.anchor },
							origin: { ...placement.origin }
						};
						const flip = key === "x" ? axis === "y" ? flipAlign : flipSide : axis === "y" ? flipSide : flipAlign;
						newPlacement.anchor = flip(newPlacement.anchor);
						newPlacement.origin = flip(newPlacement.origin);
						const { overflows: newOverflows } = checkOverflow(newPlacement);
						if (newOverflows[key].before <= overflows[key].before && newOverflows[key].after <= overflows[key].after || newOverflows[key].before + newOverflows[key].after < (overflows[key].before + overflows[key].after) / 2) {
							placement = newPlacement;
							reset = flipped[key] = true;
						}
					}
				});
				if (reset) continue;
			}
			if (overflows.x.before) {
				x += overflows.x.before;
				contentBox.x += overflows.x.before;
			}
			if (overflows.x.after) {
				x -= overflows.x.after;
				contentBox.x -= overflows.x.after;
			}
			if (overflows.y.before) {
				y += overflows.y.before;
				contentBox.y += overflows.y.before;
			}
			if (overflows.y.after) {
				y -= overflows.y.after;
				contentBox.y -= overflows.y.after;
			}
			{
				const overflows = getOverflow(contentBox, viewport);
				available.x = viewport.width - overflows.x.before - overflows.x.after;
				available.y = viewport.height - overflows.y.before - overflows.y.after;
				x += overflows.x.before;
				contentBox.x += overflows.x.before;
				y += overflows.y.before;
				contentBox.y += overflows.y.before;
			}
			break;
		}
		const axis = getAxis(placement.anchor);
		Object.assign(contentStyles.value, {
			"--v-overlay-anchor-origin": `${placement.anchor.side} ${placement.anchor.align}`,
			transformOrigin: `${placement.origin.side} ${placement.origin.align}`,
			top: convertToUnit(pixelRound(y)),
			left: data.isRtl.value ? void 0 : convertToUnit(pixelRound(x)),
			right: data.isRtl.value ? convertToUnit(pixelRound(-x)) : void 0,
			minWidth: convertToUnit(axis === "y" ? Math.min(minWidth.value, targetBox.width) : minWidth.value),
			maxWidth: convertToUnit(pixelCeil(clamp(available.x, minWidth.value === Infinity ? 0 : minWidth.value, maxWidth.value))),
			maxHeight: convertToUnit(pixelCeil(clamp(available.y, minHeight.value === Infinity ? 0 : minHeight.value, maxHeight.value)))
		});
		return {
			available,
			contentBox,
			flipped
		};
	}
	watch(() => [
		preferredAnchor.value,
		preferredOrigin.value,
		props.offset,
		props.minWidth,
		props.minHeight,
		props.maxWidth,
		props.maxHeight
	], () => updateLocation());
	nextTick(() => {
		const result = updateLocation();
		if (!result) return;
		const { available, contentBox } = result;
		if (contentBox.height > available.y) requestAnimationFrame(() => {
			updateLocation();
			requestAnimationFrame(() => {
				updateLocation();
			});
		});
	});
	return { updateLocation };
}
function pixelRound(val) {
	return Math.round(val * devicePixelRatio) / devicePixelRatio;
}
function pixelCeil(val) {
	return Math.ceil(val * devicePixelRatio) / devicePixelRatio;
}
//#endregion
//#region node_modules/vuetify/lib/components/VOverlay/requestNewFrame.js
var clean = true;
var frames = [];
/**
* Schedule a task to run in an animation frame on its own
* This is useful for heavy tasks that may cause jank if all ran together
*/
function requestNewFrame(cb) {
	if (!clean || frames.length) {
		frames.push(cb);
		run();
	} else {
		clean = false;
		cb();
		run();
	}
}
var raf = -1;
function run() {
	cancelAnimationFrame(raf);
	raf = requestAnimationFrame(() => {
		const frame = frames.shift();
		if (frame) frame();
		if (frames.length) run();
		else clean = true;
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/VOverlay/scrollStrategies.js
var scrollStrategies = {
	none: null,
	close: closeScrollStrategy,
	block: blockScrollStrategy,
	reposition: repositionScrollStrategy
};
var makeScrollStrategyProps = propsFactory({ scrollStrategy: {
	type: [String, Function],
	default: "block",
	validator: (val) => typeof val === "function" || val in scrollStrategies
} }, "VOverlay-scroll-strategies");
function useScrollStrategies(props, data) {
	if (!IN_BROWSER) return;
	let scope;
	watchEffect(async () => {
		scope?.stop();
		if (!(data.isActive.value && props.scrollStrategy)) return;
		scope = effectScope();
		await new Promise((resolve) => setTimeout(resolve));
		scope.active && scope.run(() => {
			if (typeof props.scrollStrategy === "function") props.scrollStrategy(data, props, scope);
			else scrollStrategies[props.scrollStrategy]?.(data, props, scope);
		});
	});
	onScopeDispose(() => {
		scope?.stop();
	});
}
function closeScrollStrategy(data) {
	function onScroll(e) {
		data.isActive.value = false;
	}
	bindScroll(data.targetEl.value ?? data.contentEl.value, onScroll);
}
function blockScrollStrategy(data, props) {
	const offsetParent = data.root.value?.offsetParent;
	const scrollElements = [...new Set([...getScrollParents(data.targetEl.value, props.contained ? offsetParent : void 0), ...getScrollParents(data.contentEl.value, props.contained ? offsetParent : void 0)])].filter((el) => !el.classList.contains("v-overlay-scroll-blocked"));
	const scrollbarWidth = window.innerWidth - document.documentElement.offsetWidth;
	const scrollableParent = ((el) => hasScrollbar(el) && el)(offsetParent || document.documentElement);
	if (scrollableParent) data.root.value.classList.add("v-overlay--scroll-blocked");
	scrollElements.forEach((el, i) => {
		el.style.setProperty("--v-body-scroll-x", convertToUnit(-el.scrollLeft));
		el.style.setProperty("--v-body-scroll-y", convertToUnit(-el.scrollTop));
		if (el !== document.documentElement) el.style.setProperty("--v-scrollbar-offset", convertToUnit(scrollbarWidth));
		el.classList.add("v-overlay-scroll-blocked");
	});
	onScopeDispose(() => {
		scrollElements.forEach((el, i) => {
			const x = parseFloat(el.style.getPropertyValue("--v-body-scroll-x"));
			const y = parseFloat(el.style.getPropertyValue("--v-body-scroll-y"));
			const scrollBehavior = el.style.scrollBehavior;
			el.style.scrollBehavior = "auto";
			el.style.removeProperty("--v-body-scroll-x");
			el.style.removeProperty("--v-body-scroll-y");
			el.style.removeProperty("--v-scrollbar-offset");
			el.classList.remove("v-overlay-scroll-blocked");
			el.scrollLeft = -x;
			el.scrollTop = -y;
			el.style.scrollBehavior = scrollBehavior;
		});
		if (scrollableParent) data.root.value.classList.remove("v-overlay--scroll-blocked");
	});
}
function repositionScrollStrategy(data, props, scope) {
	let slow = false;
	let raf = -1;
	let ric = -1;
	function update(e) {
		requestNewFrame(() => {
			const start = performance.now();
			data.updateLocation.value?.(e);
			slow = (performance.now() - start) / (1e3 / 60) > 2;
		});
	}
	ric = (typeof requestIdleCallback === "undefined" ? (cb) => cb() : requestIdleCallback)(() => {
		scope.run(() => {
			bindScroll(data.targetEl.value ?? data.contentEl.value, (e) => {
				if (slow) {
					cancelAnimationFrame(raf);
					raf = requestAnimationFrame(() => {
						raf = requestAnimationFrame(() => {
							update(e);
						});
					});
				} else update(e);
			});
		});
	});
	onScopeDispose(() => {
		typeof cancelIdleCallback !== "undefined" && cancelIdleCallback(ric);
		cancelAnimationFrame(raf);
	});
}
/** @private */
function bindScroll(el, onScroll) {
	const scrollElements = [document, ...getScrollParents(el)];
	scrollElements.forEach((el) => {
		el.addEventListener("scroll", onScroll, { passive: true });
	});
	onScopeDispose(() => {
		scrollElements.forEach((el) => {
			el.removeEventListener("scroll", onScroll);
		});
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/VMenu/shared.js
var VMenuSymbol = Symbol.for("vuetify:v-menu");
//#endregion
//#region node_modules/vuetify/lib/composables/delay.js
var makeDelayProps = propsFactory({
	closeDelay: [Number, String],
	openDelay: [Number, String]
}, "delay");
function useDelay(props, cb) {
	let clearDelay = () => {};
	function runDelay(isOpening) {
		clearDelay?.();
		const delay = Number(isOpening ? props.openDelay : props.closeDelay);
		return new Promise((resolve) => {
			clearDelay = defer(delay, () => {
				cb?.(isOpening);
				resolve(isOpening);
			});
		});
	}
	function runOpenDelay() {
		return runDelay(true);
	}
	function runCloseDelay() {
		return runDelay(false);
	}
	return {
		clearDelay,
		runOpenDelay,
		runCloseDelay
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VOverlay/useActivator.js
var makeActivatorProps = propsFactory({
	target: [String, Object],
	activator: [String, Object],
	activatorProps: {
		type: Object,
		default: () => ({})
	},
	openOnClick: {
		type: Boolean,
		default: void 0
	},
	openOnHover: Boolean,
	openOnFocus: {
		type: Boolean,
		default: void 0
	},
	closeOnContentClick: Boolean,
	...makeDelayProps()
}, "VOverlay-activator");
function useActivator(props, _ref) {
	let { isActive, isTop, contentEl } = _ref;
	const vm = getCurrentInstance$1("useActivator");
	const activatorEl = ref();
	let isHovered = false;
	let isFocused = false;
	let firstEnter = true;
	const openOnFocus = computed(() => props.openOnFocus || props.openOnFocus == null && props.openOnHover);
	const openOnClick = computed(() => props.openOnClick || props.openOnClick == null && !props.openOnHover && !openOnFocus.value);
	const { runOpenDelay, runCloseDelay } = useDelay(props, (value) => {
		if (value === (props.openOnHover && isHovered || openOnFocus.value && isFocused) && !(props.openOnHover && isActive.value && !isTop.value)) {
			if (isActive.value !== value) firstEnter = true;
			isActive.value = value;
		}
	});
	const cursorTarget = ref();
	const availableEvents = {
		onClick: (e) => {
			e.stopPropagation();
			activatorEl.value = e.currentTarget || e.target;
			if (!isActive.value) cursorTarget.value = [e.clientX, e.clientY];
			isActive.value = !isActive.value;
		},
		onMouseenter: (e) => {
			if (e.sourceCapabilities?.firesTouchEvents) return;
			isHovered = true;
			activatorEl.value = e.currentTarget || e.target;
			runOpenDelay();
		},
		onMouseleave: (e) => {
			isHovered = false;
			runCloseDelay();
		},
		onFocus: (e) => {
			if (matchesSelector(e.target, ":focus-visible") === false) return;
			isFocused = true;
			e.stopPropagation();
			activatorEl.value = e.currentTarget || e.target;
			runOpenDelay();
		},
		onBlur: (e) => {
			isFocused = false;
			e.stopPropagation();
			runCloseDelay();
		}
	};
	const activatorEvents = computed(() => {
		const events = {};
		if (openOnClick.value) events.onClick = availableEvents.onClick;
		if (props.openOnHover) {
			events.onMouseenter = availableEvents.onMouseenter;
			events.onMouseleave = availableEvents.onMouseleave;
		}
		if (openOnFocus.value) {
			events.onFocus = availableEvents.onFocus;
			events.onBlur = availableEvents.onBlur;
		}
		return events;
	});
	const contentEvents = computed(() => {
		const events = {};
		if (props.openOnHover) {
			events.onMouseenter = () => {
				isHovered = true;
				runOpenDelay();
			};
			events.onMouseleave = () => {
				isHovered = false;
				runCloseDelay();
			};
		}
		if (openOnFocus.value) {
			events.onFocusin = () => {
				isFocused = true;
				runOpenDelay();
			};
			events.onFocusout = () => {
				isFocused = false;
				runCloseDelay();
			};
		}
		if (props.closeOnContentClick) {
			const menu = inject(VMenuSymbol, null);
			events.onClick = () => {
				isActive.value = false;
				menu?.closeParents();
			};
		}
		return events;
	});
	const scrimEvents = computed(() => {
		const events = {};
		if (props.openOnHover) {
			events.onMouseenter = () => {
				if (firstEnter) {
					isHovered = true;
					firstEnter = false;
					runOpenDelay();
				}
			};
			events.onMouseleave = () => {
				isHovered = false;
				runCloseDelay();
			};
		}
		return events;
	});
	watch(isTop, (val) => {
		if (val && (props.openOnHover && !isHovered && (!openOnFocus.value || !isFocused) || openOnFocus.value && !isFocused && (!props.openOnHover || !isHovered)) && !contentEl.value?.contains(document.activeElement)) isActive.value = false;
	});
	watch(isActive, (val) => {
		if (!val) setTimeout(() => {
			cursorTarget.value = void 0;
		});
	}, { flush: "post" });
	const activatorRef = templateRef();
	watchEffect(() => {
		if (!activatorRef.value) return;
		nextTick(() => {
			activatorEl.value = activatorRef.el;
		});
	});
	const targetRef = templateRef();
	const target = computed(() => {
		if (props.target === "cursor" && cursorTarget.value) return cursorTarget.value;
		if (targetRef.value) return targetRef.el;
		return getTarget(props.target, vm) || activatorEl.value;
	});
	const targetEl = computed(() => {
		return Array.isArray(target.value) ? void 0 : target.value;
	});
	let scope;
	watch(() => !!props.activator, (val) => {
		if (val && IN_BROWSER) {
			scope = effectScope();
			scope.run(() => {
				_useActivator(props, vm, {
					activatorEl,
					activatorEvents
				});
			});
		} else if (scope) scope.stop();
	}, {
		flush: "post",
		immediate: true
	});
	onScopeDispose(() => {
		scope?.stop();
	});
	return {
		activatorEl,
		activatorRef,
		target,
		targetEl,
		targetRef,
		activatorEvents,
		contentEvents,
		scrimEvents
	};
}
function _useActivator(props, vm, _ref2) {
	let { activatorEl, activatorEvents } = _ref2;
	watch(() => props.activator, (val, oldVal) => {
		if (oldVal && val !== oldVal) {
			const activator = getActivator(oldVal);
			activator && unbindActivatorProps(activator);
		}
		if (val) nextTick(() => bindActivatorProps());
	}, { immediate: true });
	watch(() => props.activatorProps, () => {
		bindActivatorProps();
	});
	onScopeDispose(() => {
		unbindActivatorProps();
	});
	function bindActivatorProps() {
		let el = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : getActivator();
		let _props = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : props.activatorProps;
		if (!el) return;
		bindProps(el, mergeProps(activatorEvents.value, _props));
	}
	function unbindActivatorProps() {
		let el = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : getActivator();
		let _props = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : props.activatorProps;
		if (!el) return;
		unbindProps(el, mergeProps(activatorEvents.value, _props));
	}
	function getActivator() {
		const activator = getTarget(arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : props.activator, vm);
		activatorEl.value = activator?.nodeType === Node.ELEMENT_NODE ? activator : void 0;
		return activatorEl.value;
	}
}
function getTarget(selector, vm) {
	if (!selector) return;
	let target;
	if (selector === "parent") {
		let el = vm?.proxy?.$el?.parentNode;
		while (el?.hasAttribute("data-no-activator")) el = el.parentNode;
		target = el;
	} else if (typeof selector === "string") target = document.querySelector(selector);
	else if ("$el" in selector) target = selector.$el;
	else target = selector;
	return target;
}
//#endregion
//#region node_modules/vuetify/lib/composables/hydration.js
function useHydration() {
	if (!IN_BROWSER) return shallowRef(false);
	const { ssr } = useDisplay();
	if (ssr) {
		const isMounted = shallowRef(false);
		onMounted(() => {
			isMounted.value = true;
		});
		return isMounted;
	} else return shallowRef(true);
}
//#endregion
//#region node_modules/vuetify/lib/composables/lazy.js
var makeLazyProps = propsFactory({ eager: Boolean }, "lazy");
function useLazy(props, active) {
	const isBooted = shallowRef(false);
	const hasContent = toRef(() => isBooted.value || props.eager || active.value);
	watch(active, () => isBooted.value = true);
	function onAfterLeave() {
		if (!props.eager) isBooted.value = false;
	}
	return {
		isBooted,
		hasContent,
		onAfterLeave
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/scopeId.js
function useScopeId() {
	const scopeId = getCurrentInstance$1("useScopeId").vnode.scopeId;
	return { scopeId: scopeId ? { [scopeId]: "" } : void 0 };
}
//#endregion
//#region node_modules/vuetify/lib/composables/stack.js
var StackSymbol = Symbol.for("vuetify:stack");
var globalStack = reactive([]);
function useStack(isActive, zIndex, disableGlobalStack) {
	const vm = getCurrentInstance$1("useStack");
	const createStackEntry = !disableGlobalStack;
	const parent = inject(StackSymbol, void 0);
	const stack = reactive({ activeChildren: /* @__PURE__ */ new Set() });
	provide(StackSymbol, stack);
	const _zIndex = shallowRef(Number(toValue(zIndex)));
	useToggleScope(isActive, () => {
		const lastZIndex = globalStack.at(-1)?.[1];
		_zIndex.value = lastZIndex ? lastZIndex + 10 : Number(toValue(zIndex));
		if (createStackEntry) globalStack.push([vm.uid, _zIndex.value]);
		parent?.activeChildren.add(vm.uid);
		onScopeDispose(() => {
			if (createStackEntry) {
				const idx = toRaw(globalStack).findIndex((v) => v[0] === vm.uid);
				globalStack.splice(idx, 1);
			}
			parent?.activeChildren.delete(vm.uid);
		});
	});
	const globalTop = shallowRef(true);
	if (createStackEntry) watchEffect(() => {
		const _isTop = globalStack.at(-1)?.[0] === vm.uid;
		setTimeout(() => globalTop.value = _isTop);
	});
	const localTop = toRef(() => !stack.activeChildren.size);
	return {
		globalTop: readonly(globalTop),
		localTop,
		stackStyles: toRef(() => ({ zIndex: _zIndex.value }))
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/teleport.js
function useTeleport(target) {
	return { teleportTarget: computed(() => {
		const _target = target();
		if (_target === true || !IN_BROWSER) return void 0;
		const targetElement = _target === false ? document.body : typeof _target === "string" ? document.querySelector(_target) : _target;
		if (targetElement == null) {
			warn(`Unable to locate target ${_target}`);
			return;
		}
		let container = [...targetElement.children].find((el) => el.matches(".v-overlay-container"));
		if (!container) {
			container = document.createElement("div");
			container.className = "v-overlay-container";
			targetElement.appendChild(container);
		}
		return container;
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/directives/click-outside/index.js
function defaultConditional() {
	return true;
}
function checkEvent(e, el, binding) {
	if (!e || checkIsActive(e, binding) === false) return false;
	const root = attachedRoot(el);
	if (typeof ShadowRoot !== "undefined" && root instanceof ShadowRoot && root.host === e.target) return false;
	const elements = (typeof binding.value === "object" && binding.value.include || (() => []))();
	elements.push(el);
	return !elements.some((el) => el?.contains(e.target));
}
function checkIsActive(e, binding) {
	return (typeof binding.value === "object" && binding.value.closeConditional || defaultConditional)(e);
}
function directive(e, el, binding) {
	const handler = typeof binding.value === "function" ? binding.value : binding.value.handler;
	e.shadowTarget = e.target;
	el._clickOutside.lastMousedownWasOutside && checkEvent(e, el, binding) && setTimeout(() => {
		checkIsActive(e, binding) && handler && handler(e);
	}, 0);
}
function handleShadow(el, callback) {
	const root = attachedRoot(el);
	callback(document);
	if (typeof ShadowRoot !== "undefined" && root instanceof ShadowRoot) callback(root);
}
var ClickOutside = {
	mounted(el, binding) {
		const onClick = (e) => directive(e, el, binding);
		const onMousedown = (e) => {
			el._clickOutside.lastMousedownWasOutside = checkEvent(e, el, binding);
		};
		handleShadow(el, (app) => {
			app.addEventListener("click", onClick, true);
			app.addEventListener("mousedown", onMousedown, true);
		});
		if (!el._clickOutside) el._clickOutside = { lastMousedownWasOutside: false };
		el._clickOutside[binding.instance.$.uid] = {
			onClick,
			onMousedown
		};
	},
	beforeUnmount(el, binding) {
		if (!el._clickOutside) return;
		handleShadow(el, (app) => {
			if (!app || !el._clickOutside?.[binding.instance.$.uid]) return;
			const { onClick, onMousedown } = el._clickOutside[binding.instance.$.uid];
			app.removeEventListener("click", onClick, true);
			app.removeEventListener("mousedown", onMousedown, true);
		});
		delete el._clickOutside[binding.instance.$.uid];
	}
};
//#endregion
//#region node_modules/vuetify/lib/components/VOverlay/VOverlay.js
function Scrim(props) {
	const { modelValue, color, ...rest } = props;
	return createVNode(Transition, {
		"name": "fade-transition",
		"appear": true
	}, { default: () => [props.modelValue && createVNode("div", mergeProps({
		"class": ["v-overlay__scrim", props.color.backgroundColorClasses.value],
		"style": props.color.backgroundColorStyles.value
	}, rest), null)] });
}
var makeVOverlayProps = propsFactory({
	absolute: Boolean,
	attach: [
		Boolean,
		String,
		Object
	],
	closeOnBack: {
		type: Boolean,
		default: true
	},
	contained: Boolean,
	contentClass: null,
	contentProps: null,
	disabled: Boolean,
	opacity: [Number, String],
	noClickAnimation: Boolean,
	modelValue: Boolean,
	persistent: Boolean,
	scrim: {
		type: [Boolean, String],
		default: true
	},
	zIndex: {
		type: [Number, String],
		default: 2e3
	},
	...makeActivatorProps(),
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeLazyProps(),
	...makeLocationStrategyProps(),
	...makeScrollStrategyProps(),
	...makeThemeProps(),
	...makeTransitionProps()
}, "VOverlay");
var VOverlay = genericComponent()({
	name: "VOverlay",
	directives: { ClickOutside },
	inheritAttrs: false,
	props: {
		_disableGlobalStack: Boolean,
		...makeVOverlayProps()
	},
	emits: {
		"click:outside": (e) => true,
		"update:modelValue": (value) => true,
		keydown: (e) => true,
		afterEnter: () => true,
		afterLeave: () => true
	},
	setup(props, _ref) {
		let { slots, attrs, emit } = _ref;
		const vm = getCurrentInstance$1("VOverlay");
		const root = ref();
		const scrimEl = ref();
		const contentEl = ref();
		const model = useProxiedModel(props, "modelValue");
		const isActive = computed({
			get: () => model.value,
			set: (v) => {
				if (!(v && props.disabled)) model.value = v;
			}
		});
		const { themeClasses } = provideTheme(props);
		const { rtlClasses, isRtl } = useRtl();
		const { hasContent, onAfterLeave: _onAfterLeave } = useLazy(props, isActive);
		const scrimColor = useBackgroundColor(() => {
			return typeof props.scrim === "string" ? props.scrim : null;
		});
		const { globalTop, localTop, stackStyles } = useStack(isActive, () => props.zIndex, props._disableGlobalStack);
		const { activatorEl, activatorRef, target, targetEl, targetRef, activatorEvents, contentEvents, scrimEvents } = useActivator(props, {
			isActive,
			isTop: localTop,
			contentEl
		});
		const { teleportTarget } = useTeleport(() => {
			const target = props.attach || props.contained;
			if (target) return target;
			const rootNode = activatorEl?.value?.getRootNode() || vm.proxy?.$el?.getRootNode();
			if (rootNode instanceof ShadowRoot) return rootNode;
			return false;
		});
		const { dimensionStyles } = useDimension(props);
		const isMounted = useHydration();
		const { scopeId } = useScopeId();
		watch(() => props.disabled, (v) => {
			if (v) isActive.value = false;
		});
		const { contentStyles, updateLocation } = useLocationStrategies(props, {
			isRtl,
			contentEl,
			target,
			isActive
		});
		useScrollStrategies(props, {
			root,
			contentEl,
			targetEl,
			isActive,
			updateLocation
		});
		function onClickOutside(e) {
			emit("click:outside", e);
			if (!props.persistent) isActive.value = false;
			else animateClick();
		}
		function closeConditional(e) {
			return isActive.value && globalTop.value && (!props.scrim || e.target === scrimEl.value || e instanceof MouseEvent && e.shadowTarget === scrimEl.value);
		}
		IN_BROWSER && watch(isActive, (val) => {
			if (val) window.addEventListener("keydown", onKeydown);
			else window.removeEventListener("keydown", onKeydown);
		}, { immediate: true });
		onBeforeUnmount(() => {
			if (!IN_BROWSER) return;
			window.removeEventListener("keydown", onKeydown);
		});
		function onKeydown(e) {
			if (e.key === "Escape" && globalTop.value) {
				if (!contentEl.value?.contains(document.activeElement)) emit("keydown", e);
				if (!props.persistent) {
					isActive.value = false;
					if (contentEl.value?.contains(document.activeElement)) activatorEl.value?.focus();
				} else animateClick();
			}
		}
		function onKeydownSelf(e) {
			if (e.key === "Escape" && !globalTop.value) return;
			emit("keydown", e);
		}
		const router = useRouter();
		useToggleScope(() => props.closeOnBack, () => {
			useBackButton(router, (next) => {
				if (globalTop.value && isActive.value) {
					next(false);
					if (!props.persistent) isActive.value = false;
					else animateClick();
				} else next();
			});
		});
		const top = ref();
		watch(() => isActive.value && (props.absolute || props.contained) && teleportTarget.value == null, (val) => {
			if (val) {
				const scrollParent = getScrollParent(root.value);
				if (scrollParent && scrollParent !== document.scrollingElement) top.value = scrollParent.scrollTop;
			}
		});
		function animateClick() {
			if (props.noClickAnimation) return;
			contentEl.value && animate(contentEl.value, [
				{ transformOrigin: "center" },
				{ transform: "scale(1.03)" },
				{ transformOrigin: "center" }
			], {
				duration: 150,
				easing: "cubic-bezier(0.4, 0, 0.2, 1)"
			});
		}
		function onAfterEnter() {
			emit("afterEnter");
		}
		function onAfterLeave() {
			_onAfterLeave();
			emit("afterLeave");
		}
		useRender(() => createVNode(Fragment, null, [slots.activator?.({
			isActive: isActive.value,
			targetRef,
			props: mergeProps({ ref: activatorRef }, activatorEvents.value, props.activatorProps)
		}), isMounted.value && hasContent.value && createVNode(Teleport, {
			"disabled": !teleportTarget.value,
			"to": teleportTarget.value
		}, { default: () => [createVNode("div", mergeProps({
			"class": [
				"v-overlay",
				{
					"v-overlay--absolute": props.absolute || props.contained,
					"v-overlay--active": isActive.value,
					"v-overlay--contained": props.contained
				},
				themeClasses.value,
				rtlClasses.value,
				props.class
			],
			"style": [
				stackStyles.value,
				{
					"--v-overlay-opacity": props.opacity,
					top: convertToUnit(top.value)
				},
				props.style
			],
			"ref": root,
			"onKeydown": onKeydownSelf
		}, scopeId, attrs), [createVNode(Scrim, mergeProps({
			"color": scrimColor,
			"modelValue": isActive.value && !!props.scrim,
			"ref": scrimEl
		}, scrimEvents.value), null), createVNode(MaybeTransition, {
			"appear": true,
			"persisted": true,
			"transition": props.transition,
			"target": target.value,
			"onAfterEnter": onAfterEnter,
			"onAfterLeave": onAfterLeave
		}, { default: () => [withDirectives(createVNode("div", mergeProps({
			"ref": contentEl,
			"class": ["v-overlay__content", props.contentClass],
			"style": [dimensionStyles.value, contentStyles.value]
		}, contentEvents.value, props.contentProps), [slots.default?.({ isActive })]), [[vShow, isActive.value], [resolveDirective("click-outside"), {
			handler: onClickOutside,
			closeConditional,
			include: () => [activatorEl.value]
		}]])] })])] })]));
		return {
			activatorEl,
			scrimEl,
			target,
			animateClick,
			contentEl,
			globalTop,
			localTop,
			updateLocation
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/forwardRefs.js
var Refs = Symbol("Forwarded refs");
/** Omit properties starting with P */
/** Omit keyof $props from T */
function getDescriptor(obj, key) {
	let currentObj = obj;
	while (currentObj) {
		const descriptor = Reflect.getOwnPropertyDescriptor(currentObj, key);
		if (descriptor) return descriptor;
		currentObj = Object.getPrototypeOf(currentObj);
	}
}
function forwardRefs(target) {
	for (var _len = arguments.length, refs = new Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) refs[_key - 1] = arguments[_key];
	target[Refs] = refs;
	return new Proxy(target, {
		get(target, key) {
			if (Reflect.has(target, key)) return Reflect.get(target, key);
			if (typeof key === "symbol" || key.startsWith("$") || key.startsWith("__")) return;
			for (const ref of refs) if (ref.value && Reflect.has(ref.value, key)) {
				const val = Reflect.get(ref.value, key);
				return typeof val === "function" ? val.bind(ref.value) : val;
			}
		},
		has(target, key) {
			if (Reflect.has(target, key)) return true;
			if (typeof key === "symbol" || key.startsWith("$") || key.startsWith("__")) return false;
			for (const ref of refs) if (ref.value && Reflect.has(ref.value, key)) return true;
			return false;
		},
		set(target, key, value) {
			if (Reflect.has(target, key)) return Reflect.set(target, key, value);
			if (typeof key === "symbol" || key.startsWith("$") || key.startsWith("__")) return false;
			for (const ref of refs) if (ref.value && Reflect.has(ref.value, key)) return Reflect.set(ref.value, key, value);
			return false;
		},
		getOwnPropertyDescriptor(target, key) {
			const descriptor = Reflect.getOwnPropertyDescriptor(target, key);
			if (descriptor) return descriptor;
			if (typeof key === "symbol" || key.startsWith("$") || key.startsWith("__")) return;
			for (const ref of refs) {
				if (!ref.value) continue;
				const descriptor = getDescriptor(ref.value, key) ?? ("_" in ref.value ? getDescriptor(ref.value._?.setupState, key) : void 0);
				if (descriptor) return descriptor;
			}
			for (const ref of refs) {
				const childRefs = ref.value && ref.value[Refs];
				if (!childRefs) continue;
				const queue = childRefs.slice();
				while (queue.length) {
					const ref = queue.shift();
					const descriptor = getDescriptor(ref.value, key);
					if (descriptor) return descriptor;
					const childRefs = ref.value && ref.value[Refs];
					if (childRefs) queue.push(...childRefs);
				}
			}
		}
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/VMenu/VMenu.js
var makeVMenuProps = propsFactory({
	id: String,
	submenu: Boolean,
	...omit(makeVOverlayProps({
		closeDelay: 250,
		closeOnContentClick: true,
		locationStrategy: "connected",
		location: void 0,
		openDelay: 300,
		scrim: false,
		scrollStrategy: "reposition",
		transition: { component: VDialogTransition }
	}), ["absolute"])
}, "VMenu");
var VMenu = genericComponent()({
	name: "VMenu",
	props: makeVMenuProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const isActive = useProxiedModel(props, "modelValue");
		const { scopeId } = useScopeId();
		const { isRtl } = useRtl();
		const uid = useId();
		const id = toRef(() => props.id || `v-menu-${uid}`);
		const overlay = ref();
		const parent = inject(VMenuSymbol, null);
		const openChildren = shallowRef(/* @__PURE__ */ new Set());
		provide(VMenuSymbol, {
			register() {
				openChildren.value.add(uid);
			},
			unregister() {
				openChildren.value.delete(uid);
			},
			closeParents(e) {
				setTimeout(() => {
					if (!openChildren.value.size && !props.persistent && (e == null || overlay.value?.contentEl && !isClickInsideElement(e, overlay.value.contentEl))) {
						isActive.value = false;
						parent?.closeParents();
					}
				}, 40);
			}
		});
		onBeforeUnmount(() => {
			parent?.unregister();
			document.removeEventListener("focusin", onFocusIn);
		});
		onDeactivated(() => isActive.value = false);
		async function onFocusIn(e) {
			const before = e.relatedTarget;
			const after = e.target;
			await nextTick();
			if (isActive.value && before !== after && overlay.value?.contentEl && overlay.value?.globalTop && ![document, overlay.value.contentEl].includes(after) && !overlay.value.contentEl.contains(after)) focusableChildren(overlay.value.contentEl)[0]?.focus();
		}
		watch(isActive, (val) => {
			if (val) {
				parent?.register();
				if (IN_BROWSER) document.addEventListener("focusin", onFocusIn, { once: true });
			} else {
				parent?.unregister();
				if (IN_BROWSER) document.removeEventListener("focusin", onFocusIn);
			}
		}, { immediate: true });
		function onClickOutside(e) {
			parent?.closeParents(e);
		}
		function onKeydown(e) {
			if (props.disabled) return;
			if (e.key === "Tab" || e.key === "Enter" && !props.closeOnContentClick) {
				if (e.key === "Enter" && (e.target instanceof HTMLTextAreaElement || e.target instanceof HTMLInputElement && !!e.target.closest("form"))) return;
				if (e.key === "Enter") e.preventDefault();
				if (!getNextElement(focusableChildren(overlay.value?.contentEl, false), e.shiftKey ? "prev" : "next", (el) => el.tabIndex >= 0)) {
					isActive.value = false;
					overlay.value?.activatorEl?.focus();
				}
			} else if (props.submenu && e.key === (isRtl.value ? "ArrowRight" : "ArrowLeft")) {
				isActive.value = false;
				overlay.value?.activatorEl?.focus();
			}
		}
		function onActivatorKeydown(e) {
			if (props.disabled) return;
			const el = overlay.value?.contentEl;
			if (el && isActive.value) {
				if (e.key === "ArrowDown") {
					e.preventDefault();
					e.stopImmediatePropagation();
					focusChild(el, "next");
				} else if (e.key === "ArrowUp") {
					e.preventDefault();
					e.stopImmediatePropagation();
					focusChild(el, "prev");
				} else if (props.submenu) {
					if (e.key === (isRtl.value ? "ArrowRight" : "ArrowLeft")) isActive.value = false;
					else if (e.key === (isRtl.value ? "ArrowLeft" : "ArrowRight")) {
						e.preventDefault();
						focusChild(el, "first");
					}
				}
			} else if (props.submenu ? e.key === (isRtl.value ? "ArrowLeft" : "ArrowRight") : ["ArrowDown", "ArrowUp"].includes(e.key)) {
				isActive.value = true;
				e.preventDefault();
				setTimeout(() => setTimeout(() => onActivatorKeydown(e)));
			}
		}
		const activatorProps = computed(() => mergeProps({
			"aria-haspopup": "menu",
			"aria-expanded": String(isActive.value),
			"aria-controls": id.value,
			onKeydown: onActivatorKeydown
		}, props.activatorProps));
		useRender(() => {
			const overlayProps = VOverlay.filterProps(props);
			return createVNode(VOverlay, mergeProps({
				"ref": overlay,
				"id": id.value,
				"class": ["v-menu", props.class],
				"style": props.style
			}, overlayProps, {
				"modelValue": isActive.value,
				"onUpdate:modelValue": ($event) => isActive.value = $event,
				"absolute": true,
				"activatorProps": activatorProps.value,
				"location": props.location ?? (props.submenu ? "end" : "bottom"),
				"onClick:outside": onClickOutside,
				"onKeydown": onKeydown
			}, scopeId), {
				activator: slots.activator,
				default: function() {
					for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
					return createVNode(VDefaultsProvider, { "root": "VMenu" }, { default: () => [slots.default?.(...args)] });
				}
			});
		});
		return forwardRefs({
			id,
			ΨopenChildren: openChildren
		}, overlay);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCounter/VCounter.js
var makeVCounterProps = propsFactory({
	active: Boolean,
	disabled: Boolean,
	max: [Number, String],
	value: {
		type: [Number, String],
		default: 0
	},
	...makeComponentProps(),
	...makeTransitionProps({ transition: { component: VSlideYTransition } })
}, "VCounter");
var VCounter = genericComponent()({
	name: "VCounter",
	functional: true,
	props: makeVCounterProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const counter = toRef(() => {
			return props.max ? `${props.value} / ${props.max}` : String(props.value);
		});
		useRender(() => createVNode(MaybeTransition, { "transition": props.transition }, { default: () => [withDirectives(createVNode("div", {
			"class": [
				"v-counter",
				{ "text-error": props.max && !props.disabled && parseFloat(props.value) > parseFloat(props.max) },
				props.class
			],
			"style": props.style
		}, [slots.default ? slots.default({
			counter: counter.value,
			max: props.max,
			value: props.value
		}) : counter.value]), [[vShow, props.active]])] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VField/VFieldLabel.js
var makeVFieldLabelProps = propsFactory({
	floating: Boolean,
	...makeComponentProps()
}, "VFieldLabel");
var VFieldLabel = genericComponent()({
	name: "VFieldLabel",
	props: makeVFieldLabelProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode(VLabel, {
			"class": [
				"v-field-label",
				{ "v-field-label--floating": props.floating },
				props.class
			],
			"style": props.style,
			"aria-hidden": props.floating || void 0
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VField/VField.js
var allowedVariants$1 = [
	"underlined",
	"outlined",
	"filled",
	"solo",
	"solo-inverted",
	"solo-filled",
	"plain"
];
var makeVFieldProps = propsFactory({
	appendInnerIcon: IconValue,
	bgColor: String,
	clearable: Boolean,
	clearIcon: {
		type: IconValue,
		default: "$clear"
	},
	active: Boolean,
	centerAffix: {
		type: Boolean,
		default: void 0
	},
	color: String,
	baseColor: String,
	dirty: Boolean,
	disabled: {
		type: Boolean,
		default: null
	},
	glow: Boolean,
	error: Boolean,
	flat: Boolean,
	iconColor: [Boolean, String],
	label: String,
	persistentClear: Boolean,
	prependInnerIcon: IconValue,
	reverse: Boolean,
	singleLine: Boolean,
	variant: {
		type: String,
		default: "filled",
		validator: (v) => allowedVariants$1.includes(v)
	},
	"onClick:clear": EventProp(),
	"onClick:appendInner": EventProp(),
	"onClick:prependInner": EventProp(),
	...makeComponentProps(),
	...makeLoaderProps(),
	...makeRoundedProps(),
	...makeThemeProps()
}, "VField");
var VField = genericComponent()({
	name: "VField",
	inheritAttrs: false,
	props: {
		id: String,
		...makeFocusProps(),
		...makeVFieldProps()
	},
	emits: {
		"update:focused": (focused) => true,
		"update:modelValue": (value) => true
	},
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { loaderClasses } = useLoader(props);
		const { focusClasses, isFocused, focus, blur } = useFocus(props);
		const { InputIcon } = useInputIcon(props);
		const { roundedClasses } = useRounded(props);
		const { rtlClasses } = useRtl();
		const isActive = toRef(() => props.dirty || props.active);
		const hasLabel = toRef(() => !!(props.label || slots.label));
		const hasFloatingLabel = toRef(() => !props.singleLine && hasLabel.value);
		const uid = useId();
		const id = computed(() => props.id || `input-${uid}`);
		const messagesId = toRef(() => `${id.value}-messages`);
		const labelRef = ref();
		const floatingLabelRef = ref();
		const controlRef = ref();
		const isPlainOrUnderlined = computed(() => ["plain", "underlined"].includes(props.variant));
		const color = computed(() => {
			return props.error || props.disabled ? void 0 : isActive.value && isFocused.value ? props.color : props.baseColor;
		});
		const iconColor = computed(() => {
			if (!props.iconColor || props.glow && !isFocused.value) return void 0;
			return props.iconColor === true ? color.value : props.iconColor;
		});
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { textColorClasses, textColorStyles } = useTextColor(color);
		watch(isActive, (val) => {
			if (hasFloatingLabel.value) {
				const el = labelRef.value.$el;
				const targetEl = floatingLabelRef.value.$el;
				requestAnimationFrame(() => {
					const rect = nullifyTransforms(el);
					const targetRect = targetEl.getBoundingClientRect();
					const x = targetRect.x - rect.x;
					const y = targetRect.y - rect.y - (rect.height / 2 - targetRect.height / 2);
					const targetWidth = targetRect.width / .75;
					const width = Math.abs(targetWidth - rect.width) > 1 ? { maxWidth: convertToUnit(targetWidth) } : void 0;
					const style = getComputedStyle(el);
					const targetStyle = getComputedStyle(targetEl);
					const duration = parseFloat(style.transitionDuration) * 1e3 || 150;
					const scale = parseFloat(targetStyle.getPropertyValue("--v-field-label-scale"));
					const color = targetStyle.getPropertyValue("color");
					el.style.visibility = "visible";
					targetEl.style.visibility = "hidden";
					animate(el, {
						transform: `translate(${x}px, ${y}px) scale(${scale})`,
						color,
						...width
					}, {
						duration,
						easing: standardEasing,
						direction: val ? "normal" : "reverse"
					}).finished.then(() => {
						el.style.removeProperty("visibility");
						targetEl.style.removeProperty("visibility");
					});
				});
			}
		}, { flush: "post" });
		const slotProps = computed(() => ({
			isActive,
			isFocused,
			controlRef,
			blur,
			focus
		}));
		function onClick(e) {
			if (e.target !== document.activeElement) e.preventDefault();
		}
		useRender(() => {
			const isOutlined = props.variant === "outlined";
			const hasPrepend = !!(slots["prepend-inner"] || props.prependInnerIcon);
			const hasClear = !!(props.clearable || slots.clear) && !props.disabled;
			const hasAppend = !!(slots["append-inner"] || props.appendInnerIcon || hasClear);
			const label = () => slots.label ? slots.label({
				...slotProps.value,
				label: props.label,
				props: { for: id.value }
			}) : props.label;
			return createVNode("div", mergeProps({
				"class": [
					"v-field",
					{
						"v-field--active": isActive.value,
						"v-field--appended": hasAppend,
						"v-field--center-affix": props.centerAffix ?? !isPlainOrUnderlined.value,
						"v-field--disabled": props.disabled,
						"v-field--dirty": props.dirty,
						"v-field--error": props.error,
						"v-field--glow": props.glow,
						"v-field--flat": props.flat,
						"v-field--has-background": !!props.bgColor,
						"v-field--persistent-clear": props.persistentClear,
						"v-field--prepended": hasPrepend,
						"v-field--reverse": props.reverse,
						"v-field--single-line": props.singleLine,
						"v-field--no-label": !label(),
						[`v-field--variant-${props.variant}`]: true
					},
					themeClasses.value,
					backgroundColorClasses.value,
					focusClasses.value,
					loaderClasses.value,
					roundedClasses.value,
					rtlClasses.value,
					props.class
				],
				"style": [backgroundColorStyles.value, props.style],
				"onClick": onClick
			}, attrs), [
				createVNode("div", { "class": "v-field__overlay" }, null),
				createVNode(LoaderSlot, {
					"name": "v-field",
					"active": !!props.loading,
					"color": props.error ? "error" : typeof props.loading === "string" ? props.loading : props.color
				}, { default: slots.loader }),
				hasPrepend && createVNode("div", {
					"key": "prepend",
					"class": "v-field__prepend-inner"
				}, [props.prependInnerIcon && createVNode(InputIcon, {
					"key": "prepend-icon",
					"name": "prependInner",
					"color": iconColor.value
				}, null), slots["prepend-inner"]?.(slotProps.value)]),
				createVNode("div", {
					"class": "v-field__field",
					"data-no-activator": ""
				}, [
					[
						"filled",
						"solo",
						"solo-inverted",
						"solo-filled"
					].includes(props.variant) && hasFloatingLabel.value && createVNode(VFieldLabel, {
						"key": "floating-label",
						"ref": floatingLabelRef,
						"class": [textColorClasses.value],
						"floating": true,
						"for": id.value,
						"style": textColorStyles.value
					}, { default: () => [label()] }),
					hasLabel.value && createVNode(VFieldLabel, {
						"key": "label",
						"ref": labelRef,
						"for": id.value
					}, { default: () => [label()] }),
					slots.default?.({
						...slotProps.value,
						props: {
							id: id.value,
							class: "v-field__input",
							"aria-describedby": messagesId.value
						},
						focus,
						blur
					}) ?? createVNode("div", {
						"id": id.value,
						"class": "v-field__input",
						"aria-describedby": messagesId.value
					}, null)
				]),
				hasClear && createVNode(VExpandXTransition, { "key": "clear" }, { default: () => [withDirectives(createVNode("div", {
					"class": "v-field__clearable",
					"onMousedown": (e) => {
						e.preventDefault();
						e.stopPropagation();
					}
				}, [createVNode(VDefaultsProvider, { "defaults": { VIcon: { icon: props.clearIcon } } }, { default: () => [slots.clear ? slots.clear({
					...slotProps.value,
					props: {
						onFocus: focus,
						onBlur: blur,
						onClick: props["onClick:clear"]
					}
				}) : createVNode(InputIcon, {
					"name": "clear",
					"onFocus": focus,
					"onBlur": blur
				}, null)] })]), [[vShow, props.dirty]])] }),
				hasAppend && createVNode("div", {
					"key": "append",
					"class": "v-field__append-inner"
				}, [slots["append-inner"]?.(slotProps.value), props.appendInnerIcon && createVNode(InputIcon, {
					"key": "append-icon",
					"name": "appendInner",
					"color": iconColor.value
				}, null)]),
				createVNode("div", {
					"class": ["v-field__outline", textColorClasses.value],
					"style": textColorStyles.value
				}, [isOutlined && createVNode(Fragment, null, [
					createVNode("div", { "class": "v-field__outline__start" }, null),
					hasFloatingLabel.value && createVNode("div", { "class": "v-field__outline__notch" }, [createVNode(VFieldLabel, {
						"ref": floatingLabelRef,
						"floating": true,
						"for": id.value
					}, { default: () => [label()] })]),
					createVNode("div", { "class": "v-field__outline__end" }, null)
				]), isPlainOrUnderlined.value && hasFloatingLabel.value && createVNode(VFieldLabel, {
					"ref": floatingLabelRef,
					"floating": true,
					"for": id.value
				}, { default: () => [label()] })])
			]);
		});
		return {
			controlRef,
			fieldIconColor: iconColor
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTextField/VTextField.js
var activeTypes = [
	"color",
	"file",
	"time",
	"date",
	"datetime-local",
	"week",
	"month"
];
var makeVTextFieldProps = propsFactory({
	autofocus: Boolean,
	counter: [
		Boolean,
		Number,
		String
	],
	counterValue: [Number, Function],
	prefix: String,
	placeholder: String,
	persistentPlaceholder: Boolean,
	persistentCounter: Boolean,
	suffix: String,
	role: String,
	type: {
		type: String,
		default: "text"
	},
	modelModifiers: Object,
	...makeVInputProps(),
	...makeVFieldProps()
}, "VTextField");
var VTextField = genericComponent()({
	name: "VTextField",
	directives: { Intersect },
	inheritAttrs: false,
	props: makeVTextFieldProps(),
	emits: {
		"click:control": (e) => true,
		"mousedown:control": (e) => true,
		"update:focused": (focused) => true,
		"update:modelValue": (val) => true
	},
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const { isFocused, focus, blur } = useFocus(props);
		const counterValue = computed(() => {
			return typeof props.counterValue === "function" ? props.counterValue(model.value) : typeof props.counterValue === "number" ? props.counterValue : (model.value ?? "").toString().length;
		});
		const max = computed(() => {
			if (attrs.maxlength) return attrs.maxlength;
			if (!props.counter || typeof props.counter !== "number" && typeof props.counter !== "string") return void 0;
			return props.counter;
		});
		const isPlainOrUnderlined = computed(() => ["plain", "underlined"].includes(props.variant));
		function onIntersect(isIntersecting, entries) {
			if (!props.autofocus || !isIntersecting) return;
			entries[0].target?.focus?.();
		}
		const vInputRef = ref();
		const vFieldRef = ref();
		const inputRef = ref();
		const isActive = computed(() => activeTypes.includes(props.type) || props.persistentPlaceholder || isFocused.value || props.active);
		function onFocus() {
			if (inputRef.value !== document.activeElement) inputRef.value?.focus();
			if (!isFocused.value) focus();
		}
		function onControlMousedown(e) {
			emit("mousedown:control", e);
			if (e.target === inputRef.value) return;
			onFocus();
			e.preventDefault();
		}
		function onControlClick(e) {
			onFocus();
			emit("click:control", e);
		}
		function onClear(e, reset) {
			e.stopPropagation();
			onFocus();
			nextTick(() => {
				model.value = null;
				reset();
				callEvent(props["onClick:clear"], e);
			});
		}
		function onInput(e) {
			const el = e.target;
			model.value = el.value;
			if (props.modelModifiers?.trim && [
				"text",
				"search",
				"password",
				"tel",
				"url"
			].includes(props.type)) {
				const caretPosition = [el.selectionStart, el.selectionEnd];
				nextTick(() => {
					el.selectionStart = caretPosition[0];
					el.selectionEnd = caretPosition[1];
				});
			}
		}
		useRender(() => {
			const hasCounter = !!(slots.counter || props.counter !== false && props.counter != null);
			const hasDetails = !!(hasCounter || slots.details);
			const [rootAttrs, inputAttrs] = filterInputAttrs(attrs);
			const { modelValue: _, ...inputProps } = VInput.filterProps(props);
			const fieldProps = VField.filterProps(props);
			return createVNode(VInput, mergeProps({
				"ref": vInputRef,
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"class": [
					"v-text-field",
					{
						"v-text-field--prefixed": props.prefix,
						"v-text-field--suffixed": props.suffix,
						"v-input--plain-underlined": isPlainOrUnderlined.value
					},
					props.class
				],
				"style": props.style
			}, rootAttrs, inputProps, {
				"centerAffix": !isPlainOrUnderlined.value,
				"focused": isFocused.value
			}), {
				...slots,
				default: (_ref2) => {
					let { id, isDisabled, isDirty, isReadonly, isValid, reset } = _ref2;
					return createVNode(VField, mergeProps({
						"ref": vFieldRef,
						"onMousedown": onControlMousedown,
						"onClick": onControlClick,
						"onClick:clear": (e) => onClear(e, reset),
						"onClick:prependInner": props["onClick:prependInner"],
						"onClick:appendInner": props["onClick:appendInner"],
						"role": props.role
					}, fieldProps, {
						"id": id.value,
						"active": isActive.value || isDirty.value,
						"dirty": isDirty.value || props.dirty,
						"disabled": isDisabled.value,
						"focused": isFocused.value,
						"error": isValid.value === false
					}), {
						...slots,
						default: (_ref3) => {
							let { props: { class: fieldClass, ...slotProps } } = _ref3;
							const inputNode = withDirectives(createVNode("input", mergeProps({
								"ref": inputRef,
								"value": model.value,
								"onInput": onInput,
								"autofocus": props.autofocus,
								"readonly": isReadonly.value,
								"disabled": isDisabled.value,
								"name": props.name,
								"placeholder": props.placeholder,
								"size": 1,
								"type": props.type,
								"onFocus": onFocus,
								"onBlur": blur
							}, slotProps, inputAttrs), null), [[
								resolveDirective("intersect"),
								{ handler: onIntersect },
								null,
								{ once: true }
							]]);
							return createVNode(Fragment, null, [
								props.prefix && createVNode("span", { "class": "v-text-field__prefix" }, [createVNode("span", { "class": "v-text-field__prefix__text" }, [props.prefix])]),
								slots.default ? createVNode("div", {
									"class": fieldClass,
									"data-no-activator": ""
								}, [slots.default(), inputNode]) : cloneVNode(inputNode, { class: fieldClass }),
								props.suffix && createVNode("span", { "class": "v-text-field__suffix" }, [createVNode("span", { "class": "v-text-field__suffix__text" }, [props.suffix])])
							]);
						}
					});
				},
				details: hasDetails ? (slotProps) => createVNode(Fragment, null, [slots.details?.(slotProps), hasCounter && createVNode(Fragment, null, [createVNode("span", null, null), createVNode(VCounter, {
					"active": props.persistentCounter || isFocused.value,
					"value": counterValue.value,
					"max": max.value,
					"disabled": props.disabled
				}, slots.counter)])]) : void 0
			});
		});
		return forwardRefs({}, vInputRef, vFieldRef, inputRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VVirtualScroll/VVirtualScrollItem.js
var makeVVirtualScrollItemProps = propsFactory({
	renderless: Boolean,
	...makeComponentProps()
}, "VVirtualScrollItem");
var VVirtualScrollItem = genericComponent()({
	name: "VVirtualScrollItem",
	inheritAttrs: false,
	props: makeVVirtualScrollItemProps(),
	emits: { "update:height": (height) => true },
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const { resizeRef, contentRect } = useResizeObserver(void 0, "border");
		watch(() => contentRect.value?.height, (height) => {
			if (height != null) emit("update:height", height);
		});
		useRender(() => props.renderless ? createVNode(Fragment, null, [slots.default?.({ itemRef: resizeRef })]) : createVNode("div", mergeProps({
			"ref": resizeRef,
			"class": ["v-virtual-scroll__item", props.class],
			"style": props.style
		}, attrs), [slots.default?.()]));
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/virtual.js
var UP = -1;
var DOWN = 1;
/** Determines how large each batch of items should be */
var BUFFER_PX = 100;
var makeVirtualProps = propsFactory({
	itemHeight: {
		type: [Number, String],
		default: null
	},
	itemKey: {
		type: [
			String,
			Array,
			Function
		],
		default: null
	},
	height: [Number, String]
}, "virtual");
function useVirtual(props, items) {
	const display = useDisplay();
	const itemHeight = shallowRef(0);
	watchEffect(() => {
		itemHeight.value = parseFloat(props.itemHeight || 0);
	});
	const first = shallowRef(0);
	const last = shallowRef(Math.ceil((parseInt(props.height) || display.height.value) / (itemHeight.value || 16)) || 1);
	const paddingTop = shallowRef(0);
	const paddingBottom = shallowRef(0);
	/** The scrollable element */
	const containerRef = ref();
	/** An element marking the top of the scrollable area,
	* used to add an offset if there's padding or other elements above the virtual list */
	const markerRef = ref();
	/** markerRef's offsetTop, lazily evaluated */
	let markerOffset = 0;
	const { resizeRef, contentRect } = useResizeObserver();
	watchEffect(() => {
		resizeRef.value = containerRef.value;
	});
	const viewportHeight = computed(() => {
		return containerRef.value === document.documentElement ? display.height.value : contentRect.value?.height || parseInt(props.height) || 0;
	});
	/** All static elements have been rendered and we have an assumed item height */
	const hasInitialRender = computed(() => {
		return !!(containerRef.value && markerRef.value && viewportHeight.value && itemHeight.value);
	});
	let sizes = Array.from({ length: items.value.length });
	let offsets = Array.from({ length: items.value.length });
	const updateTime = shallowRef(0);
	let targetScrollIndex = -1;
	function getSize(index) {
		return sizes[index] || itemHeight.value;
	}
	const updateOffsets = debounce(() => {
		const start = performance.now();
		offsets[0] = 0;
		const length = items.value.length;
		for (let i = 1; i <= length - 1; i++) offsets[i] = (offsets[i - 1] || 0) + getSize(i - 1);
		updateTime.value = Math.max(updateTime.value, performance.now() - start);
	}, updateTime);
	const unwatch = watch(hasInitialRender, (v) => {
		if (!v) return;
		unwatch();
		markerOffset = markerRef.value.offsetTop;
		updateOffsets.immediate();
		calculateVisibleItems();
		if (!~targetScrollIndex) return;
		nextTick(() => {
			IN_BROWSER && window.requestAnimationFrame(() => {
				scrollToIndex(targetScrollIndex);
				targetScrollIndex = -1;
			});
		});
	});
	onScopeDispose(() => {
		updateOffsets.clear();
	});
	function handleItemResize(index, height) {
		const prevHeight = sizes[index];
		const prevMinHeight = itemHeight.value;
		itemHeight.value = prevMinHeight ? Math.min(itemHeight.value, height) : height;
		if (prevHeight !== height || prevMinHeight !== itemHeight.value) {
			sizes[index] = height;
			updateOffsets();
		}
	}
	function calculateOffset(index) {
		index = clamp(index, 0, items.value.length - 1);
		return offsets[index] || 0;
	}
	function calculateIndex(scrollTop) {
		return binaryClosest(offsets, scrollTop);
	}
	let lastScrollTop = 0;
	let scrollVelocity = 0;
	let lastScrollTime = 0;
	watch(viewportHeight, (val, oldVal) => {
		if (oldVal) {
			calculateVisibleItems();
			if (val < oldVal) requestAnimationFrame(() => {
				scrollVelocity = 0;
				calculateVisibleItems();
			});
		}
	});
	let scrollTimeout = -1;
	function handleScroll() {
		if (!containerRef.value || !markerRef.value) return;
		const scrollTop = containerRef.value.scrollTop;
		const scrollTime = performance.now();
		if (scrollTime - lastScrollTime > 500) {
			scrollVelocity = Math.sign(scrollTop - lastScrollTop);
			markerOffset = markerRef.value.offsetTop;
		} else scrollVelocity = scrollTop - lastScrollTop;
		lastScrollTop = scrollTop;
		lastScrollTime = scrollTime;
		window.clearTimeout(scrollTimeout);
		scrollTimeout = window.setTimeout(handleScrollend, 500);
		calculateVisibleItems();
	}
	function handleScrollend() {
		if (!containerRef.value || !markerRef.value) return;
		scrollVelocity = 0;
		lastScrollTime = 0;
		window.clearTimeout(scrollTimeout);
		calculateVisibleItems();
	}
	let raf = -1;
	function calculateVisibleItems() {
		cancelAnimationFrame(raf);
		raf = requestAnimationFrame(_calculateVisibleItems);
	}
	function _calculateVisibleItems() {
		if (!containerRef.value || !viewportHeight.value) return;
		const scrollTop = lastScrollTop - markerOffset;
		const direction = Math.sign(scrollVelocity);
		const start = clamp(calculateIndex(Math.max(0, scrollTop - BUFFER_PX)), 0, items.value.length);
		const end = clamp(calculateIndex(scrollTop + viewportHeight.value + BUFFER_PX) + 1, start + 1, items.value.length);
		if ((direction !== UP || start < first.value) && (direction !== DOWN || end > last.value)) {
			const topOverflow = calculateOffset(first.value) - calculateOffset(start);
			const bottomOverflow = calculateOffset(end) - calculateOffset(last.value);
			if (Math.max(topOverflow, bottomOverflow) > BUFFER_PX) {
				first.value = start;
				last.value = end;
			} else {
				if (start <= 0) first.value = start;
				if (end >= items.value.length) last.value = end;
			}
		}
		paddingTop.value = calculateOffset(first.value);
		paddingBottom.value = calculateOffset(items.value.length) - calculateOffset(last.value);
	}
	function scrollToIndex(index) {
		const offset = calculateOffset(index);
		if (!containerRef.value || index && !offset) targetScrollIndex = index;
		else containerRef.value.scrollTop = offset;
	}
	const computedItems = computed(() => {
		return items.value.slice(first.value, last.value).map((item, index) => {
			const _index = index + first.value;
			return {
				raw: item,
				index: _index,
				key: getPropertyFromItem(item, props.itemKey, _index)
			};
		});
	});
	watch(items, () => {
		sizes = Array.from({ length: items.value.length });
		offsets = Array.from({ length: items.value.length });
		updateOffsets.immediate();
		calculateVisibleItems();
	}, { deep: 1 });
	return {
		calculateVisibleItems,
		containerRef,
		markerRef,
		computedItems,
		paddingTop,
		paddingBottom,
		scrollToIndex,
		handleScroll,
		handleScrollend,
		handleItemResize
	};
}
function binaryClosest(arr, val) {
	let high = arr.length - 1;
	let low = 0;
	let mid = 0;
	let item = null;
	let target = -1;
	if (arr[high] < val) return high;
	while (low <= high) {
		mid = low + high >> 1;
		item = arr[mid];
		if (item > val) high = mid - 1;
		else if (item < val) {
			target = mid;
			low = mid + 1;
		} else if (item === val) return mid;
		else return low;
	}
	return target;
}
//#endregion
//#region node_modules/vuetify/lib/components/VVirtualScroll/VVirtualScroll.js
var makeVVirtualScrollProps = propsFactory({
	items: {
		type: Array,
		default: () => []
	},
	renderless: Boolean,
	...makeVirtualProps(),
	...makeComponentProps(),
	...makeDimensionProps()
}, "VVirtualScroll");
var VVirtualScroll = genericComponent()({
	name: "VVirtualScroll",
	props: makeVVirtualScrollProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const vm = getCurrentInstance$1("VVirtualScroll");
		const { dimensionStyles } = useDimension(props);
		const { calculateVisibleItems, containerRef, markerRef, handleScroll, handleScrollend, handleItemResize, scrollToIndex, paddingTop, paddingBottom, computedItems } = useVirtual(props, toRef(() => props.items));
		useToggleScope(() => props.renderless, () => {
			function handleListeners() {
				const method = (arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : false) ? "addEventListener" : "removeEventListener";
				if (containerRef.value === document.documentElement) {
					document[method]("scroll", handleScroll, { passive: true });
					document[method]("scrollend", handleScrollend);
				} else {
					containerRef.value?.[method]("scroll", handleScroll, { passive: true });
					containerRef.value?.[method]("scrollend", handleScrollend);
				}
			}
			onMounted(() => {
				containerRef.value = getScrollParent(vm.vnode.el, true);
				handleListeners(true);
			});
			onScopeDispose(handleListeners);
		});
		useRender(() => {
			const children = computedItems.value.map((item) => createVNode(VVirtualScrollItem, {
				"key": item.key,
				"renderless": props.renderless,
				"onUpdate:height": (height) => handleItemResize(item.index, height)
			}, { default: (slotProps) => slots.default?.({
				item: item.raw,
				index: item.index,
				...slotProps
			}) }));
			return props.renderless ? createVNode(Fragment, null, [
				createVNode("div", {
					"ref": markerRef,
					"class": "v-virtual-scroll__spacer",
					"style": { paddingTop: convertToUnit(paddingTop.value) }
				}, null),
				children,
				createVNode("div", {
					"class": "v-virtual-scroll__spacer",
					"style": { paddingBottom: convertToUnit(paddingBottom.value) }
				}, null)
			]) : createVNode("div", {
				"ref": containerRef,
				"class": ["v-virtual-scroll", props.class],
				"onScrollPassive": handleScroll,
				"onScrollend": handleScrollend,
				"style": [dimensionStyles.value, props.style]
			}, [createVNode("div", {
				"ref": markerRef,
				"class": "v-virtual-scroll__container",
				"style": {
					paddingTop: convertToUnit(paddingTop.value),
					paddingBottom: convertToUnit(paddingBottom.value)
				}
			}, [children])]);
		});
		return {
			calculateVisibleItems,
			scrollToIndex
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSelect/useScrolling.js
function useScrolling(listRef, textFieldRef) {
	const isScrolling = shallowRef(false);
	let scrollTimeout;
	function onListScroll(e) {
		cancelAnimationFrame(scrollTimeout);
		isScrolling.value = true;
		scrollTimeout = requestAnimationFrame(() => {
			scrollTimeout = requestAnimationFrame(() => {
				isScrolling.value = false;
			});
		});
	}
	async function finishScrolling() {
		await new Promise((resolve) => requestAnimationFrame(resolve));
		await new Promise((resolve) => requestAnimationFrame(resolve));
		await new Promise((resolve) => requestAnimationFrame(resolve));
		await new Promise((resolve) => {
			if (isScrolling.value) {
				const stop = watch(isScrolling, () => {
					stop();
					resolve();
				});
			} else resolve();
		});
	}
	async function onListKeydown(e) {
		if (e.key === "Tab") textFieldRef.value?.focus();
		if (![
			"PageDown",
			"PageUp",
			"Home",
			"End"
		].includes(e.key)) return;
		const el = listRef.value?.$el;
		if (!el) return;
		if (e.key === "Home" || e.key === "End") el.scrollTo({
			top: e.key === "Home" ? 0 : el.scrollHeight,
			behavior: "smooth"
		});
		await finishScrolling();
		const children = el.querySelectorAll(":scope > :not(.v-virtual-scroll__spacer)");
		if (e.key === "PageDown" || e.key === "Home") {
			const top = el.getBoundingClientRect().top;
			for (const child of children) if (child.getBoundingClientRect().top >= top) {
				child.focus();
				break;
			}
		} else {
			const bottom = el.getBoundingClientRect().bottom;
			for (const child of [...children].reverse()) if (child.getBoundingClientRect().bottom <= bottom) {
				child.focus();
				break;
			}
		}
	}
	return {
		onScrollPassive: onListScroll,
		onKeydown: onListKeydown
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VSelect/VSelect.js
var makeSelectProps = propsFactory({
	chips: Boolean,
	closableChips: Boolean,
	closeText: {
		type: String,
		default: "$vuetify.close"
	},
	openText: {
		type: String,
		default: "$vuetify.open"
	},
	eager: Boolean,
	hideNoData: Boolean,
	hideSelected: Boolean,
	listProps: { type: Object },
	menu: Boolean,
	menuIcon: {
		type: IconValue,
		default: "$dropdown"
	},
	menuProps: { type: Object },
	multiple: Boolean,
	noDataText: {
		type: String,
		default: "$vuetify.noDataText"
	},
	openOnClear: Boolean,
	itemColor: String,
	...makeItemsProps({ itemChildren: false })
}, "Select");
var makeVSelectProps = propsFactory({
	...makeSelectProps(),
	...omit(makeVTextFieldProps({
		modelValue: null,
		role: "combobox"
	}), [
		"validationValue",
		"dirty",
		"appendInnerIcon"
	]),
	...makeTransitionProps({ transition: { component: VDialogTransition } })
}, "VSelect");
var VSelect = genericComponent()({
	name: "VSelect",
	props: makeVSelectProps(),
	emits: {
		"update:focused": (focused) => true,
		"update:modelValue": (value) => true,
		"update:menu": (ue) => true
	},
	setup(props, _ref) {
		let { slots } = _ref;
		const { t } = useLocale();
		const vTextFieldRef = ref();
		const vMenuRef = ref();
		const vVirtualScrollRef = ref();
		const { items, transformIn, transformOut } = useItems(props);
		const model = useProxiedModel(props, "modelValue", [], (v) => transformIn(v === null ? [null] : wrapInArray(v)), (v) => {
			const transformed = transformOut(v);
			return props.multiple ? transformed : transformed[0] ?? null;
		});
		const counterValue = computed(() => {
			return typeof props.counterValue === "function" ? props.counterValue(model.value) : typeof props.counterValue === "number" ? props.counterValue : model.value.length;
		});
		const form = useForm$1(props);
		const selectedValues = computed(() => model.value.map((selection) => selection.value));
		const isFocused = shallowRef(false);
		let keyboardLookupPrefix = "";
		let keyboardLookupLastTime;
		const displayItems = computed(() => {
			if (props.hideSelected) return items.value.filter((item) => !model.value.some((s) => (props.valueComparator || deepEqual)(s, item)));
			return items.value;
		});
		const menuDisabled = computed(() => props.hideNoData && !displayItems.value.length || form.isReadonly.value || form.isDisabled.value);
		const _menu = useProxiedModel(props, "menu");
		const menu = computed({
			get: () => _menu.value,
			set: (v) => {
				if (_menu.value && !v && vMenuRef.value?.ΨopenChildren.size) return;
				if (v && menuDisabled.value) return;
				_menu.value = v;
			}
		});
		const label = toRef(() => menu.value ? props.closeText : props.openText);
		const computedMenuProps = computed(() => {
			return {
				...props.menuProps,
				activatorProps: {
					...props.menuProps?.activatorProps || {},
					"aria-haspopup": "listbox"
				}
			};
		});
		const listRef = ref();
		const listEvents = useScrolling(listRef, vTextFieldRef);
		function onClear(e) {
			if (props.openOnClear) menu.value = true;
		}
		function onMousedownControl() {
			if (menuDisabled.value) return;
			menu.value = !menu.value;
		}
		function onListKeydown(e) {
			if (checkPrintable(e)) onKeydown(e);
		}
		function onKeydown(e) {
			if (!e.key || form.isReadonly.value) return;
			if ([
				"Enter",
				" ",
				"ArrowDown",
				"ArrowUp",
				"Home",
				"End"
			].includes(e.key)) e.preventDefault();
			if ([
				"Enter",
				"ArrowDown",
				" "
			].includes(e.key)) menu.value = true;
			if (["Escape", "Tab"].includes(e.key)) menu.value = false;
			if (e.key === "Home") listRef.value?.focus("first");
			else if (e.key === "End") listRef.value?.focus("last");
			const KEYBOARD_LOOKUP_THRESHOLD = 1e3;
			if (!checkPrintable(e)) return;
			const now = performance.now();
			if (now - keyboardLookupLastTime > KEYBOARD_LOOKUP_THRESHOLD) keyboardLookupPrefix = "";
			keyboardLookupPrefix += e.key.toLowerCase();
			keyboardLookupLastTime = now;
			const item = items.value.find((item) => item.title.toLowerCase().startsWith(keyboardLookupPrefix));
			if (item !== void 0) {
				model.value = [item];
				const index = displayItems.value.indexOf(item);
				IN_BROWSER && window.requestAnimationFrame(() => {
					index >= 0 && vVirtualScrollRef.value?.scrollToIndex(index);
				});
			}
		}
		/** @param set - null means toggle */
		function select(item) {
			let set = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : true;
			if (item.props.disabled) return;
			if (props.multiple) {
				const index = model.value.findIndex((selection) => (props.valueComparator || deepEqual)(selection.value, item.value));
				const add = set == null ? !~index : set;
				if (~index) {
					const value = add ? [...model.value, item] : [...model.value];
					value.splice(index, 1);
					model.value = value;
				} else if (add) model.value = [...model.value, item];
			} else {
				model.value = set !== false ? [item] : [];
				nextTick(() => {
					menu.value = false;
				});
			}
		}
		function onBlur(e) {
			if (!listRef.value?.$el.contains(e.relatedTarget)) menu.value = false;
		}
		function onAfterEnter() {
			if (props.eager) vVirtualScrollRef.value?.calculateVisibleItems();
		}
		function onAfterLeave() {
			if (isFocused.value) vTextFieldRef.value?.focus();
		}
		function onFocusin(e) {
			isFocused.value = true;
		}
		function onModelUpdate(v) {
			if (v == null) model.value = [];
			else if (matchesSelector(vTextFieldRef.value, ":autofill") || matchesSelector(vTextFieldRef.value, ":-webkit-autofill")) {
				const item = items.value.find((item) => item.title === v);
				if (item) select(item);
			} else if (vTextFieldRef.value) vTextFieldRef.value.value = "";
		}
		watch(menu, () => {
			if (!props.hideSelected && menu.value && model.value.length) {
				const index = displayItems.value.findIndex((item) => model.value.some((s) => (props.valueComparator || deepEqual)(s.value, item.value)));
				IN_BROWSER && window.requestAnimationFrame(() => {
					index >= 0 && vVirtualScrollRef.value?.scrollToIndex(index);
				});
			}
		});
		watch(() => props.items, (newVal, oldVal) => {
			if (menu.value) return;
			if (isFocused.value && !oldVal.length && newVal.length) menu.value = true;
		});
		useRender(() => {
			const hasChips = !!(props.chips || slots.chip);
			const hasList = !!(!props.hideNoData || displayItems.value.length || slots["prepend-item"] || slots["append-item"] || slots["no-data"]);
			const isDirty = model.value.length > 0;
			const textFieldProps = VTextField.filterProps(props);
			const placeholder = isDirty || !isFocused.value && props.label && !props.persistentPlaceholder ? void 0 : props.placeholder;
			return createVNode(VTextField, mergeProps({ "ref": vTextFieldRef }, textFieldProps, {
				"modelValue": model.value.map((v) => v.props.value).join(", "),
				"onUpdate:modelValue": onModelUpdate,
				"focused": isFocused.value,
				"onUpdate:focused": ($event) => isFocused.value = $event,
				"validationValue": model.externalValue,
				"counterValue": counterValue.value,
				"dirty": isDirty,
				"class": [
					"v-select",
					{
						"v-select--active-menu": menu.value,
						"v-select--chips": !!props.chips,
						[`v-select--${props.multiple ? "multiple" : "single"}`]: true,
						"v-select--selected": model.value.length,
						"v-select--selection-slot": !!slots.selection
					},
					props.class
				],
				"style": props.style,
				"inputmode": "none",
				"placeholder": placeholder,
				"onClick:clear": onClear,
				"onMousedown:control": onMousedownControl,
				"onBlur": onBlur,
				"onKeydown": onKeydown,
				"aria-label": t(label.value),
				"title": t(label.value)
			}), {
				...slots,
				default: () => createVNode(Fragment, null, [createVNode(VMenu, mergeProps({
					"ref": vMenuRef,
					"modelValue": menu.value,
					"onUpdate:modelValue": ($event) => menu.value = $event,
					"activator": "parent",
					"contentClass": "v-select__content",
					"disabled": menuDisabled.value,
					"eager": props.eager,
					"maxHeight": 310,
					"openOnClick": false,
					"closeOnContentClick": false,
					"transition": props.transition,
					"onAfterEnter": onAfterEnter,
					"onAfterLeave": onAfterLeave
				}, computedMenuProps.value), { default: () => [hasList && createVNode(VList, mergeProps({
					"ref": listRef,
					"selected": selectedValues.value,
					"selectStrategy": props.multiple ? "independent" : "single-independent",
					"onMousedown": (e) => e.preventDefault(),
					"onKeydown": onListKeydown,
					"onFocusin": onFocusin,
					"tabindex": "-1",
					"aria-live": "polite",
					"aria-label": `${props.label}-list`,
					"color": props.itemColor ?? props.color
				}, listEvents, props.listProps), { default: () => [
					slots["prepend-item"]?.(),
					!displayItems.value.length && !props.hideNoData && (slots["no-data"]?.() ?? createVNode(VListItem, {
						"key": "no-data",
						"title": t(props.noDataText)
					}, null)),
					createVNode(VVirtualScroll, {
						"ref": vVirtualScrollRef,
						"renderless": true,
						"items": displayItems.value,
						"itemKey": "value"
					}, { default: (_ref2) => {
						let { item, index, itemRef } = _ref2;
						const itemProps = mergeProps(item.props, {
							ref: itemRef,
							key: item.value,
							onClick: () => select(item, null)
						});
						return slots.item?.({
							item,
							index,
							props: itemProps
						}) ?? createVNode(VListItem, mergeProps(itemProps, { "role": "option" }), { prepend: (_ref3) => {
							let { isSelected } = _ref3;
							return createVNode(Fragment, null, [
								props.multiple && !props.hideSelected ? createVNode(VCheckboxBtn, {
									"key": item.value,
									"modelValue": isSelected,
									"ripple": false,
									"tabindex": "-1"
								}, null) : void 0,
								item.props.prependAvatar && createVNode(VAvatar, { "image": item.props.prependAvatar }, null),
								item.props.prependIcon && createVNode(VIcon, { "icon": item.props.prependIcon }, null)
							]);
						} });
					} }),
					slots["append-item"]?.()
				] })] }), model.value.map((item, index) => {
					function onChipClose(e) {
						e.stopPropagation();
						e.preventDefault();
						select(item, false);
					}
					const slotProps = {
						"onClick:close": onChipClose,
						onKeydown(e) {
							if (e.key !== "Enter" && e.key !== " ") return;
							e.preventDefault();
							e.stopPropagation();
							onChipClose(e);
						},
						onMousedown(e) {
							e.preventDefault();
							e.stopPropagation();
						},
						modelValue: true,
						"onUpdate:modelValue": void 0
					};
					const hasSlot = hasChips ? !!slots.chip : !!slots.selection;
					const slotContent = hasSlot ? ensureValidVNode(hasChips ? slots.chip({
						item,
						index,
						props: slotProps
					}) : slots.selection({
						item,
						index
					})) : void 0;
					if (hasSlot && !slotContent) return void 0;
					return createVNode("div", {
						"key": item.value,
						"class": "v-select__selection"
					}, [hasChips ? !slots.chip ? createVNode(VChip, mergeProps({
						"key": "chip",
						"closable": props.closableChips,
						"size": "small",
						"text": item.title,
						"disabled": item.props.disabled
					}, slotProps), null) : createVNode(VDefaultsProvider, {
						"key": "chip-defaults",
						"defaults": { VChip: {
							closable: props.closableChips,
							size: "small",
							text: item.title
						} }
					}, { default: () => [slotContent] }) : slotContent ?? createVNode("span", { "class": "v-select__selection-text" }, [item.title, props.multiple && index < model.value.length - 1 && createVNode("span", { "class": "v-select__selection-comma" }, [createTextVNode(",")])])]);
				})]),
				"append-inner": function() {
					for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
					return createVNode(Fragment, null, [slots["append-inner"]?.(...args), props.menuIcon ? createVNode(VIcon, {
						"class": "v-select__menu-icon",
						"color": vTextFieldRef.value?.fieldIconColor,
						"icon": props.menuIcon
					}, null) : void 0]);
				}
			});
		});
		return forwardRefs({
			isFocused,
			menu,
			select
		}, vTextFieldRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/filter.js
/**
* - boolean: match without highlight
* - number: single match (index), length already known
* - []: single match (start, end)
* - [][]: multiple matches (start, end), shouldn't overlap
*/
var defaultFilter = (value, query, item) => {
	if (value == null || query == null) return -1;
	if (!query.length) return 0;
	value = value.toString().toLocaleLowerCase();
	query = query.toString().toLocaleLowerCase();
	const result = [];
	let idx = value.indexOf(query);
	while (~idx) {
		result.push([idx, idx + query.length]);
		idx = value.indexOf(query, idx + query.length);
	}
	return result.length ? result : -1;
};
function normaliseMatch(match, query) {
	if (match == null || typeof match === "boolean" || match === -1) return;
	if (typeof match === "number") return [[match, match + query.length]];
	if (Array.isArray(match[0])) return match;
	return [match];
}
var makeFilterProps = propsFactory({
	customFilter: Function,
	customKeyFilter: Object,
	filterKeys: [Array, String],
	filterMode: {
		type: String,
		default: "intersection"
	},
	noFilter: Boolean
}, "filter");
function filterItems(items, query, options) {
	const array = [];
	const filter = options?.default ?? defaultFilter;
	const keys = options?.filterKeys ? wrapInArray(options.filterKeys) : false;
	const customFiltersLength = Object.keys(options?.customKeyFilter ?? {}).length;
	if (!items?.length) return array;
	loop: for (let i = 0; i < items.length; i++) {
		const [item, transformed = item] = wrapInArray(items[i]);
		const customMatches = {};
		const defaultMatches = {};
		let match = -1;
		if ((query || customFiltersLength > 0) && !options?.noFilter) {
			if (typeof item === "object") {
				const filterKeys = keys || Object.keys(transformed);
				for (const key of filterKeys) {
					const value = getPropertyFromItem(transformed, key);
					const keyFilter = options?.customKeyFilter?.[key];
					match = keyFilter ? keyFilter(value, query, item) : filter(value, query, item);
					if (match !== -1 && match !== false) if (keyFilter) customMatches[key] = normaliseMatch(match, query);
					else defaultMatches[key] = normaliseMatch(match, query);
					else if (options?.filterMode === "every") continue loop;
				}
			} else {
				match = filter(item, query, item);
				if (match !== -1 && match !== false) defaultMatches.title = normaliseMatch(match, query);
			}
			const defaultMatchesLength = Object.keys(defaultMatches).length;
			const customMatchesLength = Object.keys(customMatches).length;
			if (!defaultMatchesLength && !customMatchesLength) continue;
			if (options?.filterMode === "union" && customMatchesLength !== customFiltersLength && !defaultMatchesLength) continue;
			if (options?.filterMode === "intersection" && (customMatchesLength !== customFiltersLength || !defaultMatchesLength)) continue;
		}
		array.push({
			index: i,
			matches: {
				...defaultMatches,
				...customMatches
			}
		});
	}
	return array;
}
function useFilter(props, items, query, options) {
	const filteredItems = shallowRef([]);
	const filteredMatches = shallowRef(/* @__PURE__ */ new Map());
	const transformedItems = computed(() => options?.transform ? unref(items).map((item) => [item, options.transform(item)]) : unref(items));
	watchEffect(() => {
		const _query = typeof query === "function" ? query() : unref(query);
		const strQuery = typeof _query !== "string" && typeof _query !== "number" ? "" : String(_query);
		const results = filterItems(transformedItems.value, strQuery, {
			customKeyFilter: {
				...props.customKeyFilter,
				...unref(options?.customKeyFilter)
			},
			default: props.customFilter,
			filterKeys: props.filterKeys,
			filterMode: props.filterMode,
			noFilter: props.noFilter
		});
		const originalItems = unref(items);
		const _filteredItems = [];
		const _filteredMatches = /* @__PURE__ */ new Map();
		results.forEach((_ref) => {
			let { index, matches } = _ref;
			const item = originalItems[index];
			_filteredItems.push(item);
			_filteredMatches.set(item.value, matches);
		});
		filteredItems.value = _filteredItems;
		filteredMatches.value = _filteredMatches;
	});
	function getMatches(item) {
		return filteredMatches.value.get(item.value);
	}
	return {
		filteredItems,
		filteredMatches,
		getMatches
	};
}
function highlightResult(name, text, matches) {
	if (matches == null || !matches.length) return text;
	return matches.map((match, i) => {
		const start = i === 0 ? 0 : matches[i - 1][1];
		const result = [createVNode("span", { "class": `${name}__unmask` }, [text.slice(start, match[0])]), createVNode("span", { "class": `${name}__mask` }, [text.slice(match[0], match[1])])];
		if (i === matches.length - 1) result.push(createVNode("span", { "class": `${name}__unmask` }, [text.slice(match[1])]));
		return createVNode(Fragment, null, [result]);
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/VAutocomplete/VAutocomplete.js
var makeVAutocompleteProps = propsFactory({
	autoSelectFirst: { type: [Boolean, String] },
	clearOnSelect: Boolean,
	search: String,
	...makeFilterProps({ filterKeys: ["title"] }),
	...makeSelectProps(),
	...omit(makeVTextFieldProps({
		modelValue: null,
		role: "combobox"
	}), [
		"validationValue",
		"dirty",
		"appendInnerIcon"
	]),
	...makeTransitionProps({ transition: false })
}, "VAutocomplete");
var VAutocomplete = genericComponent()({
	name: "VAutocomplete",
	props: makeVAutocompleteProps(),
	emits: {
		"update:focused": (focused) => true,
		"update:search": (value) => true,
		"update:modelValue": (value) => true,
		"update:menu": (value) => true
	},
	setup(props, _ref) {
		let { slots } = _ref;
		const { t } = useLocale();
		const vTextFieldRef = ref();
		const isFocused = shallowRef(false);
		const isPristine = shallowRef(true);
		const listHasFocus = shallowRef(false);
		const vMenuRef = ref();
		const vVirtualScrollRef = ref();
		const selectionIndex = shallowRef(-1);
		const { items, transformIn, transformOut } = useItems(props);
		const { textColorClasses, textColorStyles } = useTextColor(() => vTextFieldRef.value?.color);
		const search = useProxiedModel(props, "search", "");
		const model = useProxiedModel(props, "modelValue", [], (v) => transformIn(v === null ? [null] : wrapInArray(v)), (v) => {
			const transformed = transformOut(v);
			return props.multiple ? transformed : transformed[0] ?? null;
		});
		const counterValue = computed(() => {
			return typeof props.counterValue === "function" ? props.counterValue(model.value) : typeof props.counterValue === "number" ? props.counterValue : model.value.length;
		});
		const form = useForm$1(props);
		const { filteredItems, getMatches } = useFilter(props, items, () => isPristine.value ? "" : search.value);
		const displayItems = computed(() => {
			if (props.hideSelected) return filteredItems.value.filter((filteredItem) => !model.value.some((s) => s.value === filteredItem.value));
			return filteredItems.value;
		});
		const hasChips = computed(() => !!(props.chips || slots.chip));
		const hasSelectionSlot = computed(() => hasChips.value || !!slots.selection);
		const selectedValues = computed(() => model.value.map((selection) => selection.props.value));
		const highlightFirst = computed(() => {
			return (props.autoSelectFirst === true || props.autoSelectFirst === "exact" && search.value === displayItems.value[0]?.title) && displayItems.value.length > 0 && !isPristine.value && !listHasFocus.value;
		});
		const menuDisabled = computed(() => props.hideNoData && !displayItems.value.length || form.isReadonly.value || form.isDisabled.value);
		const _menu = useProxiedModel(props, "menu");
		const menu = computed({
			get: () => _menu.value,
			set: (v) => {
				if (_menu.value && !v && vMenuRef.value?.ΨopenChildren.size) return;
				if (v && menuDisabled.value) return;
				_menu.value = v;
			}
		});
		const label = computed(() => menu.value ? props.closeText : props.openText);
		const listRef = ref();
		const listEvents = useScrolling(listRef, vTextFieldRef);
		function onClear(e) {
			if (props.openOnClear) menu.value = true;
			search.value = "";
		}
		function onMousedownControl() {
			if (menuDisabled.value) return;
			menu.value = true;
		}
		function onMousedownMenuIcon(e) {
			if (menuDisabled.value) return;
			if (isFocused.value) {
				e.preventDefault();
				e.stopPropagation();
			}
			menu.value = !menu.value;
		}
		function onListKeydown(e) {
			if (e.key !== " " && checkPrintable(e)) vTextFieldRef.value?.focus();
		}
		function onKeydown(e) {
			if (form.isReadonly.value) return;
			const selectionStart = vTextFieldRef.value?.selectionStart;
			const length = model.value.length;
			if ([
				"Enter",
				"ArrowDown",
				"ArrowUp"
			].includes(e.key)) e.preventDefault();
			if (["Enter", "ArrowDown"].includes(e.key)) menu.value = true;
			if (["Escape"].includes(e.key)) menu.value = false;
			if (highlightFirst.value && ["Enter", "Tab"].includes(e.key) && !model.value.some((_ref2) => {
				let { value } = _ref2;
				return value === displayItems.value[0].value;
			})) select(displayItems.value[0]);
			if (e.key === "ArrowDown" && highlightFirst.value) listRef.value?.focus("next");
			if (["Backspace", "Delete"].includes(e.key)) {
				if (!props.multiple && hasSelectionSlot.value && model.value.length > 0 && !search.value) return select(model.value[0], false);
				if (~selectionIndex.value) {
					e.preventDefault();
					const originalSelectionIndex = selectionIndex.value;
					select(model.value[selectionIndex.value], false);
					selectionIndex.value = originalSelectionIndex >= length - 1 ? length - 2 : originalSelectionIndex;
				} else if (e.key === "Backspace" && !search.value) selectionIndex.value = length - 1;
				return;
			}
			if (!props.multiple) return;
			if (e.key === "ArrowLeft") {
				if (selectionIndex.value < 0 && selectionStart && selectionStart > 0) return;
				const prev = selectionIndex.value > -1 ? selectionIndex.value - 1 : length - 1;
				if (model.value[prev]) selectionIndex.value = prev;
				else {
					const searchLength = search.value?.length ?? null;
					selectionIndex.value = -1;
					vTextFieldRef.value?.setSelectionRange(searchLength, searchLength);
				}
			} else if (e.key === "ArrowRight") {
				if (selectionIndex.value < 0) return;
				const next = selectionIndex.value + 1;
				if (model.value[next]) selectionIndex.value = next;
				else {
					selectionIndex.value = -1;
					vTextFieldRef.value?.setSelectionRange(0, 0);
				}
			} else if (~selectionIndex.value && checkPrintable(e)) selectionIndex.value = -1;
		}
		function onChange(e) {
			if (matchesSelector(vTextFieldRef.value, ":autofill") || matchesSelector(vTextFieldRef.value, ":-webkit-autofill")) {
				const item = items.value.find((item) => item.title === e.target.value);
				if (item) select(item);
			}
		}
		function onAfterEnter() {
			if (props.eager) vVirtualScrollRef.value?.calculateVisibleItems();
		}
		function onAfterLeave() {
			if (isFocused.value) {
				isPristine.value = true;
				vTextFieldRef.value?.focus();
			}
		}
		function onFocusin(e) {
			isFocused.value = true;
			setTimeout(() => {
				listHasFocus.value = true;
			});
		}
		function onFocusout(e) {
			listHasFocus.value = false;
		}
		function onUpdateModelValue(v) {
			if (v == null || v === "" && !props.multiple && !hasSelectionSlot.value) model.value = [];
		}
		const isSelecting = shallowRef(false);
		/** @param set - null means toggle */
		function select(item) {
			let set = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : true;
			if (!item || item.props.disabled) return;
			if (props.multiple) {
				const index = model.value.findIndex((selection) => (props.valueComparator || deepEqual)(selection.value, item.value));
				const add = set == null ? !~index : set;
				if (~index) {
					const value = add ? [...model.value, item] : [...model.value];
					value.splice(index, 1);
					model.value = value;
				} else if (add) model.value = [...model.value, item];
				if (props.clearOnSelect) search.value = "";
			} else {
				const add = set !== false;
				model.value = add ? [item] : [];
				search.value = add && !hasSelectionSlot.value ? item.title : "";
				nextTick(() => {
					menu.value = false;
					isPristine.value = true;
				});
			}
		}
		watch(isFocused, (val, oldVal) => {
			if (val === oldVal) return;
			if (val) {
				isSelecting.value = true;
				search.value = props.multiple || hasSelectionSlot.value ? "" : String(model.value.at(-1)?.props.title ?? "");
				isPristine.value = true;
				nextTick(() => isSelecting.value = false);
			} else {
				if (!props.multiple && search.value == null) model.value = [];
				menu.value = false;
				if (props.multiple || hasSelectionSlot.value) search.value = "";
				selectionIndex.value = -1;
			}
		});
		watch(search, (val) => {
			if (!isFocused.value || isSelecting.value) return;
			if (val) menu.value = true;
			isPristine.value = !val;
		});
		watch(menu, () => {
			if (!props.hideSelected && menu.value && model.value.length) {
				const index = displayItems.value.findIndex((item) => model.value.some((s) => item.value === s.value));
				IN_BROWSER && window.requestAnimationFrame(() => {
					index >= 0 && vVirtualScrollRef.value?.scrollToIndex(index);
				});
			}
		});
		watch(() => props.items, (newVal, oldVal) => {
			if (menu.value) return;
			if (isFocused.value && !oldVal.length && newVal.length) menu.value = true;
		});
		useRender(() => {
			const hasList = !!(!props.hideNoData || displayItems.value.length || slots["prepend-item"] || slots["append-item"] || slots["no-data"]);
			const isDirty = model.value.length > 0;
			const textFieldProps = VTextField.filterProps(props);
			return createVNode(VTextField, mergeProps({ "ref": vTextFieldRef }, textFieldProps, {
				"modelValue": search.value,
				"onUpdate:modelValue": [($event) => search.value = $event, onUpdateModelValue],
				"focused": isFocused.value,
				"onUpdate:focused": ($event) => isFocused.value = $event,
				"validationValue": model.externalValue,
				"counterValue": counterValue.value,
				"dirty": isDirty,
				"onChange": onChange,
				"class": [
					"v-autocomplete",
					`v-autocomplete--${props.multiple ? "multiple" : "single"}`,
					{
						"v-autocomplete--active-menu": menu.value,
						"v-autocomplete--chips": !!props.chips,
						"v-autocomplete--selection-slot": !!hasSelectionSlot.value,
						"v-autocomplete--selecting-index": selectionIndex.value > -1
					},
					props.class
				],
				"style": props.style,
				"readonly": form.isReadonly.value,
				"placeholder": isDirty ? void 0 : props.placeholder,
				"onClick:clear": onClear,
				"onMousedown:control": onMousedownControl,
				"onKeydown": onKeydown
			}), {
				...slots,
				default: () => createVNode(Fragment, null, [createVNode(VMenu, mergeProps({
					"ref": vMenuRef,
					"modelValue": menu.value,
					"onUpdate:modelValue": ($event) => menu.value = $event,
					"activator": "parent",
					"contentClass": "v-autocomplete__content",
					"disabled": menuDisabled.value,
					"eager": props.eager,
					"maxHeight": 310,
					"openOnClick": false,
					"closeOnContentClick": false,
					"transition": props.transition,
					"onAfterEnter": onAfterEnter,
					"onAfterLeave": onAfterLeave
				}, props.menuProps), { default: () => [hasList && createVNode(VList, mergeProps({
					"ref": listRef,
					"selected": selectedValues.value,
					"selectStrategy": props.multiple ? "independent" : "single-independent",
					"onMousedown": (e) => e.preventDefault(),
					"onKeydown": onListKeydown,
					"onFocusin": onFocusin,
					"onFocusout": onFocusout,
					"tabindex": "-1",
					"aria-live": "polite",
					"color": props.itemColor ?? props.color
				}, listEvents, props.listProps), { default: () => [
					slots["prepend-item"]?.(),
					!displayItems.value.length && !props.hideNoData && (slots["no-data"]?.() ?? createVNode(VListItem, {
						"key": "no-data",
						"title": t(props.noDataText)
					}, null)),
					createVNode(VVirtualScroll, {
						"ref": vVirtualScrollRef,
						"renderless": true,
						"items": displayItems.value,
						"itemKey": "value"
					}, { default: (_ref3) => {
						let { item, index, itemRef } = _ref3;
						const itemProps = mergeProps(item.props, {
							ref: itemRef,
							key: item.value,
							active: highlightFirst.value && index === 0 ? true : void 0,
							onClick: () => select(item, null)
						});
						return slots.item?.({
							item,
							index,
							props: itemProps
						}) ?? createVNode(VListItem, mergeProps(itemProps, { "role": "option" }), {
							prepend: (_ref4) => {
								let { isSelected } = _ref4;
								return createVNode(Fragment, null, [
									props.multiple && !props.hideSelected ? createVNode(VCheckboxBtn, {
										"key": item.value,
										"modelValue": isSelected,
										"ripple": false,
										"tabindex": "-1"
									}, null) : void 0,
									item.props.prependAvatar && createVNode(VAvatar, { "image": item.props.prependAvatar }, null),
									item.props.prependIcon && createVNode(VIcon, { "icon": item.props.prependIcon }, null)
								]);
							},
							title: () => {
								return isPristine.value ? item.title : highlightResult("v-autocomplete", item.title, getMatches(item)?.title);
							}
						});
					} }),
					slots["append-item"]?.()
				] })] }), model.value.map((item, index) => {
					function onChipClose(e) {
						e.stopPropagation();
						e.preventDefault();
						select(item, false);
					}
					const slotProps = {
						"onClick:close": onChipClose,
						onKeydown(e) {
							if (e.key !== "Enter" && e.key !== " ") return;
							e.preventDefault();
							e.stopPropagation();
							onChipClose(e);
						},
						onMousedown(e) {
							e.preventDefault();
							e.stopPropagation();
						},
						modelValue: true,
						"onUpdate:modelValue": void 0
					};
					const hasSlot = hasChips.value ? !!slots.chip : !!slots.selection;
					const slotContent = hasSlot ? ensureValidVNode(hasChips.value ? slots.chip({
						item,
						index,
						props: slotProps
					}) : slots.selection({
						item,
						index
					})) : void 0;
					if (hasSlot && !slotContent) return void 0;
					return createVNode("div", {
						"key": item.value,
						"class": ["v-autocomplete__selection", index === selectionIndex.value && ["v-autocomplete__selection--selected", textColorClasses.value]],
						"style": index === selectionIndex.value ? textColorStyles.value : {}
					}, [hasChips.value ? !slots.chip ? createVNode(VChip, mergeProps({
						"key": "chip",
						"closable": props.closableChips,
						"size": "small",
						"text": item.title,
						"disabled": item.props.disabled
					}, slotProps), null) : createVNode(VDefaultsProvider, {
						"key": "chip-defaults",
						"defaults": { VChip: {
							closable: props.closableChips,
							size: "small",
							text: item.title
						} }
					}, { default: () => [slotContent] }) : slotContent ?? createVNode("span", { "class": "v-autocomplete__selection-text" }, [item.title, props.multiple && index < model.value.length - 1 && createVNode("span", { "class": "v-autocomplete__selection-comma" }, [createTextVNode(",")])])]);
				})]),
				"append-inner": function() {
					for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
					return createVNode(Fragment, null, [slots["append-inner"]?.(...args), props.menuIcon ? createVNode(VIcon, {
						"class": "v-autocomplete__menu-icon",
						"color": vTextFieldRef.value?.fieldIconColor,
						"icon": props.menuIcon,
						"onMousedown": onMousedownMenuIcon,
						"onClick": noop,
						"aria-label": t(label.value),
						"title": t(label.value),
						"tabindex": "-1"
					}, null) : void 0]);
				}
			});
		});
		return forwardRefs({
			isFocused,
			isPristine,
			menu,
			search,
			filteredItems,
			select
		}, vTextFieldRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBadge/VBadge.js
var makeVBadgeProps = propsFactory({
	bordered: Boolean,
	color: String,
	content: [Number, String],
	dot: Boolean,
	floating: Boolean,
	icon: IconValue,
	inline: Boolean,
	label: {
		type: String,
		default: "$vuetify.badge"
	},
	max: [Number, String],
	modelValue: {
		type: Boolean,
		default: true
	},
	offsetX: [Number, String],
	offsetY: [Number, String],
	textColor: String,
	...makeComponentProps(),
	...makeLocationProps({ location: "top end" }),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps(),
	...makeTransitionProps({ transition: "scale-rotate-transition" })
}, "VBadge");
var VBadge = genericComponent()({
	name: "VBadge",
	inheritAttrs: false,
	props: makeVBadgeProps(),
	setup(props, ctx) {
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { roundedClasses } = useRounded(props);
		const { t } = useLocale();
		const { textColorClasses, textColorStyles } = useTextColor(() => props.textColor);
		const { themeClasses } = useTheme();
		const { locationStyles } = useLocation(props, true, (side) => {
			return (props.floating ? props.dot ? 2 : 4 : props.dot ? 8 : 12) + (["top", "bottom"].includes(side) ? Number(props.offsetY ?? 0) : ["left", "right"].includes(side) ? Number(props.offsetX ?? 0) : 0);
		});
		useRender(() => {
			const value = Number(props.content);
			const content = !props.max || isNaN(value) ? props.content : value <= Number(props.max) ? value : `${props.max}+`;
			const [badgeAttrs, attrs] = pickWithRest(ctx.attrs, [
				"aria-atomic",
				"aria-label",
				"aria-live",
				"role",
				"title"
			]);
			return createVNode(props.tag, mergeProps({ "class": [
				"v-badge",
				{
					"v-badge--bordered": props.bordered,
					"v-badge--dot": props.dot,
					"v-badge--floating": props.floating,
					"v-badge--inline": props.inline
				},
				props.class
			] }, attrs, { "style": props.style }), { default: () => [createVNode("div", { "class": "v-badge__wrapper" }, [ctx.slots.default?.(), createVNode(MaybeTransition, { "transition": props.transition }, { default: () => [withDirectives(createVNode("span", mergeProps({
				"class": [
					"v-badge__badge",
					themeClasses.value,
					backgroundColorClasses.value,
					roundedClasses.value,
					textColorClasses.value
				],
				"style": [
					backgroundColorStyles.value,
					textColorStyles.value,
					props.inline ? {} : locationStyles.value
				],
				"aria-atomic": "true",
				"aria-label": t(props.label, value),
				"aria-live": "polite",
				"role": "status"
			}, badgeAttrs), [props.dot ? void 0 : ctx.slots.badge ? ctx.slots.badge?.() : props.icon ? createVNode(VIcon, { "icon": props.icon }, null) : content]), [[vShow, props.modelValue]])] })])] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBanner/VBannerActions.js
var makeVBannerActionsProps = propsFactory({
	color: String,
	density: String,
	...makeComponentProps()
}, "VBannerActions");
var VBannerActions = genericComponent()({
	name: "VBannerActions",
	props: makeVBannerActionsProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		provideDefaults({ VBtn: {
			color: props.color,
			density: props.density,
			slim: true,
			variant: "text"
		} });
		useRender(() => createVNode("div", {
			"class": ["v-banner-actions", props.class],
			"style": props.style
		}, [slots.default?.()]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBanner/VBannerText.js
var VBannerText = createSimpleFunctional("v-banner-text");
//#endregion
//#region node_modules/vuetify/lib/components/VBanner/VBanner.js
var makeVBannerProps = propsFactory({
	avatar: String,
	bgColor: String,
	color: String,
	icon: IconValue,
	lines: String,
	stacked: Boolean,
	sticky: Boolean,
	text: String,
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeDimensionProps(),
	...makeDisplayProps({ mobile: null }),
	...makeElevationProps(),
	...makeLocationProps(),
	...makePositionProps(),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VBanner");
var VBanner = genericComponent()({
	name: "VBanner",
	props: makeVBannerProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { borderClasses } = useBorder(props);
		const { densityClasses } = useDensity(props);
		const { displayClasses, mobile } = useDisplay(props);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { locationStyles } = useLocation(props);
		const { positionClasses } = usePosition(props);
		const { roundedClasses } = useRounded(props);
		const { themeClasses } = provideTheme(props);
		const color = toRef(() => props.color);
		const density = toRef(() => props.density);
		provideDefaults({ VBannerActions: {
			color,
			density
		} });
		useRender(() => {
			const hasText = !!(props.text || slots.text);
			const hasPrependMedia = !!(props.avatar || props.icon);
			const hasPrepend = !!(hasPrependMedia || slots.prepend);
			return createVNode(props.tag, {
				"class": [
					"v-banner",
					{
						"v-banner--stacked": props.stacked || mobile.value,
						"v-banner--sticky": props.sticky,
						[`v-banner--${props.lines}-line`]: !!props.lines
					},
					themeClasses.value,
					backgroundColorClasses.value,
					borderClasses.value,
					densityClasses.value,
					displayClasses.value,
					elevationClasses.value,
					positionClasses.value,
					roundedClasses.value,
					props.class
				],
				"style": [
					backgroundColorStyles.value,
					dimensionStyles.value,
					locationStyles.value,
					props.style
				],
				"role": "banner"
			}, { default: () => [
				hasPrepend && createVNode("div", {
					"key": "prepend",
					"class": "v-banner__prepend"
				}, [!slots.prepend ? createVNode(VAvatar, {
					"key": "prepend-avatar",
					"color": color.value,
					"density": density.value,
					"icon": props.icon,
					"image": props.avatar
				}, null) : createVNode(VDefaultsProvider, {
					"key": "prepend-defaults",
					"disabled": !hasPrependMedia,
					"defaults": { VAvatar: {
						color: color.value,
						density: density.value,
						icon: props.icon,
						image: props.avatar
					} }
				}, slots.prepend)]),
				createVNode("div", { "class": "v-banner__content" }, [hasText && createVNode(VBannerText, { "key": "text" }, { default: () => [slots.text?.() ?? props.text] }), slots.default?.()]),
				slots.actions && createVNode(VBannerActions, { "key": "actions" }, slots.actions)
			] });
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBottomNavigation/VBottomNavigation.js
var makeVBottomNavigationProps = propsFactory({
	baseColor: String,
	bgColor: String,
	color: String,
	grow: Boolean,
	mode: {
		type: String,
		validator: (v) => !v || ["horizontal", "shift"].includes(v)
	},
	height: {
		type: [Number, String],
		default: 56
	},
	active: {
		type: Boolean,
		default: true
	},
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeElevationProps(),
	...makeRoundedProps(),
	...makeLayoutItemProps({ name: "bottom-navigation" }),
	...makeTagProps({ tag: "header" }),
	...makeGroupProps({ selectedClass: "v-btn--selected" }),
	...makeThemeProps()
}, "VBottomNavigation");
var VBottomNavigation = genericComponent()({
	name: "VBottomNavigation",
	props: makeVBottomNavigationProps(),
	emits: {
		"update:active": (value) => true,
		"update:modelValue": (value) => true
	},
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = useTheme();
		const { borderClasses } = useBorder(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { densityClasses } = useDensity(props);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		const { ssrBootStyles } = useSsrBoot();
		const height = computed(() => Number(props.height) - (props.density === "comfortable" ? 8 : 0) - (props.density === "compact" ? 16 : 0));
		const isActive = useProxiedModel(props, "active", props.active);
		const { layoutItemStyles } = useLayoutItem({
			id: props.name,
			order: computed(() => parseInt(props.order, 10)),
			position: toRef(() => "bottom"),
			layoutSize: toRef(() => isActive.value ? height.value : 0),
			elementSize: height,
			active: isActive,
			absolute: toRef(() => props.absolute)
		});
		useGroup(props, VBtnToggleSymbol);
		provideDefaults({ VBtn: {
			baseColor: toRef(() => props.baseColor),
			color: toRef(() => props.color),
			density: toRef(() => props.density),
			stacked: toRef(() => props.mode !== "horizontal"),
			variant: "text"
		} }, { scoped: true });
		useRender(() => {
			return createVNode(props.tag, {
				"class": [
					"v-bottom-navigation",
					{
						"v-bottom-navigation--active": isActive.value,
						"v-bottom-navigation--grow": props.grow,
						"v-bottom-navigation--shift": props.mode === "shift"
					},
					themeClasses.value,
					backgroundColorClasses.value,
					borderClasses.value,
					densityClasses.value,
					elevationClasses.value,
					roundedClasses.value,
					props.class
				],
				"style": [
					backgroundColorStyles.value,
					layoutItemStyles.value,
					{ height: convertToUnit(height.value) },
					ssrBootStyles.value,
					props.style
				]
			}, { default: () => [slots.default && createVNode("div", { "class": "v-bottom-navigation__content" }, [slots.default()])] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDialog/VDialog.js
var makeVDialogProps = propsFactory({
	fullscreen: Boolean,
	retainFocus: {
		type: Boolean,
		default: true
	},
	scrollable: Boolean,
	...makeVOverlayProps({
		origin: "center center",
		scrollStrategy: "block",
		transition: { component: VDialogTransition },
		zIndex: 2400
	})
}, "VDialog");
var VDialog = genericComponent()({
	name: "VDialog",
	props: makeVDialogProps(),
	emits: {
		"update:modelValue": (value) => true,
		afterEnter: () => true,
		afterLeave: () => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const isActive = useProxiedModel(props, "modelValue");
		const { scopeId } = useScopeId();
		const overlay = ref();
		function onFocusin(e) {
			const before = e.relatedTarget;
			const after = e.target;
			if (before !== after && overlay.value?.contentEl && overlay.value?.globalTop && ![document, overlay.value.contentEl].includes(after) && !overlay.value.contentEl.contains(after)) {
				const focusable = focusableChildren(overlay.value.contentEl);
				if (!focusable.length) return;
				const firstElement = focusable[0];
				const lastElement = focusable[focusable.length - 1];
				if (before === firstElement) lastElement.focus();
				else firstElement.focus();
			}
		}
		onBeforeUnmount(() => {
			document.removeEventListener("focusin", onFocusin);
		});
		if (IN_BROWSER) watch(() => isActive.value && props.retainFocus, (val) => {
			val ? document.addEventListener("focusin", onFocusin) : document.removeEventListener("focusin", onFocusin);
		}, { immediate: true });
		function onAfterEnter() {
			emit("afterEnter");
			if ((props.scrim || props.retainFocus) && overlay.value?.contentEl && !overlay.value.contentEl.contains(document.activeElement)) overlay.value.contentEl.focus({ preventScroll: true });
		}
		function onAfterLeave() {
			emit("afterLeave");
		}
		watch(isActive, async (val) => {
			if (!val) {
				await nextTick();
				overlay.value.activatorEl?.focus({ preventScroll: true });
			}
		});
		useRender(() => {
			const overlayProps = VOverlay.filterProps(props);
			const activatorProps = mergeProps({ "aria-haspopup": "dialog" }, props.activatorProps);
			const contentProps = mergeProps({ tabindex: -1 }, props.contentProps);
			return createVNode(VOverlay, mergeProps({
				"ref": overlay,
				"class": [
					"v-dialog",
					{
						"v-dialog--fullscreen": props.fullscreen,
						"v-dialog--scrollable": props.scrollable
					},
					props.class
				],
				"style": props.style
			}, overlayProps, {
				"modelValue": isActive.value,
				"onUpdate:modelValue": ($event) => isActive.value = $event,
				"aria-modal": "true",
				"activatorProps": activatorProps,
				"contentProps": contentProps,
				"height": !props.fullscreen ? props.height : void 0,
				"width": !props.fullscreen ? props.width : void 0,
				"maxHeight": !props.fullscreen ? props.maxHeight : void 0,
				"maxWidth": !props.fullscreen ? props.maxWidth : void 0,
				"role": "dialog",
				"onAfterEnter": onAfterEnter,
				"onAfterLeave": onAfterLeave
			}, scopeId), {
				activator: slots.activator,
				default: function() {
					for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
					return createVNode(VDefaultsProvider, { "root": "VDialog" }, { default: () => [slots.default?.(...args)] });
				}
			});
		});
		return forwardRefs({}, overlay);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBottomSheet/VBottomSheet.js
var makeVBottomSheetProps = propsFactory({
	inset: Boolean,
	...makeVDialogProps({ transition: "bottom-sheet-transition" })
}, "VBottomSheet");
var VBottomSheet = genericComponent()({
	name: "VBottomSheet",
	props: makeVBottomSheetProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const isActive = useProxiedModel(props, "modelValue");
		useRender(() => {
			return createVNode(VDialog, mergeProps(VDialog.filterProps(props), {
				"contentClass": ["v-bottom-sheet__content", props.contentClass],
				"modelValue": isActive.value,
				"onUpdate:modelValue": ($event) => isActive.value = $event,
				"class": [
					"v-bottom-sheet",
					{ "v-bottom-sheet--inset": props.inset },
					props.class
				],
				"style": props.style
			}), slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBreadcrumbs/VBreadcrumbsDivider.js
var makeVBreadcrumbsDividerProps = propsFactory({
	divider: [Number, String],
	...makeComponentProps()
}, "VBreadcrumbsDivider");
var VBreadcrumbsDivider = genericComponent()({
	name: "VBreadcrumbsDivider",
	props: makeVBreadcrumbsDividerProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode("li", {
			"aria-hidden": "true",
			"class": ["v-breadcrumbs-divider", props.class],
			"style": props.style
		}, [slots?.default?.() ?? props.divider]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBreadcrumbs/VBreadcrumbsItem.js
var makeVBreadcrumbsItemProps = propsFactory({
	active: Boolean,
	activeClass: String,
	activeColor: String,
	color: String,
	disabled: Boolean,
	title: String,
	...makeComponentProps(),
	...makeRouterProps(),
	...makeTagProps({ tag: "li" })
}, "VBreadcrumbsItem");
var VBreadcrumbsItem = genericComponent()({
	name: "VBreadcrumbsItem",
	props: makeVBreadcrumbsItemProps(),
	setup(props, _ref) {
		let { slots, attrs } = _ref;
		const link = useLink(props, attrs);
		const isActive = computed(() => props.active || link.isActive?.value);
		const { textColorClasses, textColorStyles } = useTextColor(() => isActive.value ? props.activeColor : props.color);
		useRender(() => {
			return createVNode(props.tag, {
				"class": [
					"v-breadcrumbs-item",
					{
						"v-breadcrumbs-item--active": isActive.value,
						"v-breadcrumbs-item--disabled": props.disabled,
						[`${props.activeClass}`]: isActive.value && props.activeClass
					},
					textColorClasses.value,
					props.class
				],
				"style": [textColorStyles.value, props.style],
				"aria-current": isActive.value ? "page" : void 0
			}, { default: () => [!link.isLink.value ? slots.default?.() ?? props.title : createVNode("a", mergeProps({
				"class": "v-breadcrumbs-item--link",
				"onClick": link.navigate
			}, link.linkProps), [slots.default?.() ?? props.title])] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VBreadcrumbs/VBreadcrumbs.js
var makeVBreadcrumbsProps = propsFactory({
	activeClass: String,
	activeColor: String,
	bgColor: String,
	color: String,
	disabled: Boolean,
	divider: {
		type: String,
		default: "/"
	},
	icon: IconValue,
	items: {
		type: Array,
		default: () => []
	},
	...makeComponentProps(),
	...makeDensityProps(),
	...makeRoundedProps(),
	...makeTagProps({ tag: "ul" })
}, "VBreadcrumbs");
var VBreadcrumbs = genericComponent()({
	name: "VBreadcrumbs",
	props: makeVBreadcrumbsProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { densityClasses } = useDensity(props);
		const { roundedClasses } = useRounded(props);
		provideDefaults({
			VBreadcrumbsDivider: { divider: toRef(() => props.divider) },
			VBreadcrumbsItem: {
				activeClass: toRef(() => props.activeClass),
				activeColor: toRef(() => props.activeColor),
				color: toRef(() => props.color),
				disabled: toRef(() => props.disabled)
			}
		});
		const items = computed(() => props.items.map((item) => {
			return typeof item === "string" ? {
				item: { title: item },
				raw: item
			} : {
				item,
				raw: item
			};
		}));
		useRender(() => {
			const hasPrepend = !!(slots.prepend || props.icon);
			return createVNode(props.tag, {
				"class": [
					"v-breadcrumbs",
					backgroundColorClasses.value,
					densityClasses.value,
					roundedClasses.value,
					props.class
				],
				"style": [backgroundColorStyles.value, props.style]
			}, { default: () => [
				hasPrepend && createVNode("li", {
					"key": "prepend",
					"class": "v-breadcrumbs__prepend"
				}, [!slots.prepend ? createVNode(VIcon, {
					"key": "prepend-icon",
					"start": true,
					"icon": props.icon
				}, null) : createVNode(VDefaultsProvider, {
					"key": "prepend-defaults",
					"disabled": !props.icon,
					"defaults": { VIcon: {
						icon: props.icon,
						start: true
					} }
				}, slots.prepend)]),
				items.value.map((_ref2, index, array) => {
					let { item, raw } = _ref2;
					return createVNode(Fragment, null, [slots.item?.({
						item,
						index
					}) ?? createVNode(VBreadcrumbsItem, mergeProps({
						"key": index,
						"disabled": index >= array.length - 1
					}, typeof item === "string" ? { title: item } : item), { default: slots.title ? () => slots.title?.({
						item,
						index
					}) : void 0 }), index < array.length - 1 && createVNode(VBreadcrumbsDivider, null, { default: slots.divider ? () => slots.divider?.({
						item: raw,
						index
					}) : void 0 })]);
				}),
				slots.default?.()
			] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCard/VCardActions.js
var VCardActions = genericComponent()({
	name: "VCardActions",
	props: makeComponentProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		provideDefaults({ VBtn: {
			slim: true,
			variant: "text"
		} });
		useRender(() => createVNode("div", {
			"class": ["v-card-actions", props.class],
			"style": props.style
		}, [slots.default?.()]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCard/VCardSubtitle.js
var makeVCardSubtitleProps = propsFactory({
	opacity: [Number, String],
	...makeComponentProps(),
	...makeTagProps()
}, "VCardSubtitle");
var VCardSubtitle = genericComponent()({
	name: "VCardSubtitle",
	props: makeVCardSubtitleProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode(props.tag, {
			"class": ["v-card-subtitle", props.class],
			"style": [{ "--v-card-subtitle-opacity": props.opacity }, props.style]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCard/VCardTitle.js
var VCardTitle = createSimpleFunctional("v-card-title");
//#endregion
//#region node_modules/vuetify/lib/components/VCard/VCardItem.js
var makeCardItemProps = propsFactory({
	appendAvatar: String,
	appendIcon: IconValue,
	prependAvatar: String,
	prependIcon: IconValue,
	subtitle: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	title: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	...makeComponentProps(),
	...makeDensityProps()
}, "VCardItem");
var VCardItem = genericComponent()({
	name: "VCardItem",
	props: makeCardItemProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => {
			const hasPrependMedia = !!(props.prependAvatar || props.prependIcon);
			const hasPrepend = !!(hasPrependMedia || slots.prepend);
			const hasAppendMedia = !!(props.appendAvatar || props.appendIcon);
			const hasAppend = !!(hasAppendMedia || slots.append);
			const hasTitle = !!(props.title != null || slots.title);
			const hasSubtitle = !!(props.subtitle != null || slots.subtitle);
			return createVNode("div", {
				"class": ["v-card-item", props.class],
				"style": props.style
			}, [
				hasPrepend && createVNode("div", {
					"key": "prepend",
					"class": "v-card-item__prepend"
				}, [!slots.prepend ? createVNode(Fragment, null, [props.prependAvatar && createVNode(VAvatar, {
					"key": "prepend-avatar",
					"density": props.density,
					"image": props.prependAvatar
				}, null), props.prependIcon && createVNode(VIcon, {
					"key": "prepend-icon",
					"density": props.density,
					"icon": props.prependIcon
				}, null)]) : createVNode(VDefaultsProvider, {
					"key": "prepend-defaults",
					"disabled": !hasPrependMedia,
					"defaults": {
						VAvatar: {
							density: props.density,
							image: props.prependAvatar
						},
						VIcon: {
							density: props.density,
							icon: props.prependIcon
						}
					}
				}, slots.prepend)]),
				createVNode("div", { "class": "v-card-item__content" }, [
					hasTitle && createVNode(VCardTitle, { "key": "title" }, { default: () => [slots.title?.() ?? toDisplayString(props.title)] }),
					hasSubtitle && createVNode(VCardSubtitle, { "key": "subtitle" }, { default: () => [slots.subtitle?.() ?? toDisplayString(props.subtitle)] }),
					slots.default?.()
				]),
				hasAppend && createVNode("div", {
					"key": "append",
					"class": "v-card-item__append"
				}, [!slots.append ? createVNode(Fragment, null, [props.appendIcon && createVNode(VIcon, {
					"key": "append-icon",
					"density": props.density,
					"icon": props.appendIcon
				}, null), props.appendAvatar && createVNode(VAvatar, {
					"key": "append-avatar",
					"density": props.density,
					"image": props.appendAvatar
				}, null)]) : createVNode(VDefaultsProvider, {
					"key": "append-defaults",
					"disabled": !hasAppendMedia,
					"defaults": {
						VAvatar: {
							density: props.density,
							image: props.appendAvatar
						},
						VIcon: {
							density: props.density,
							icon: props.appendIcon
						}
					}
				}, slots.append)])
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCard/VCardText.js
var makeVCardTextProps = propsFactory({
	opacity: [Number, String],
	...makeComponentProps(),
	...makeTagProps()
}, "VCardText");
var VCardText = genericComponent()({
	name: "VCardText",
	props: makeVCardTextProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => createVNode(props.tag, {
			"class": ["v-card-text", props.class],
			"style": [{ "--v-card-text-opacity": props.opacity }, props.style]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCard/VCard.js
var makeVCardProps = propsFactory({
	appendAvatar: String,
	appendIcon: IconValue,
	disabled: Boolean,
	flat: Boolean,
	hover: Boolean,
	image: String,
	link: {
		type: Boolean,
		default: void 0
	},
	prependAvatar: String,
	prependIcon: IconValue,
	ripple: {
		type: [Boolean, Object],
		default: true
	},
	subtitle: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	text: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	title: {
		type: [
			String,
			Number,
			Boolean
		],
		default: void 0
	},
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeDimensionProps(),
	...makeElevationProps(),
	...makeLoaderProps(),
	...makeLocationProps(),
	...makePositionProps(),
	...makeRoundedProps(),
	...makeRouterProps(),
	...makeTagProps(),
	...makeThemeProps(),
	...makeVariantProps({ variant: "elevated" })
}, "VCard");
var VCard = genericComponent()({
	name: "VCard",
	directives: { Ripple },
	props: makeVCardProps(),
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { borderClasses } = useBorder(props);
		const { colorClasses, colorStyles, variantClasses } = useVariant(props);
		const { densityClasses } = useDensity(props);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { loaderClasses } = useLoader(props);
		const { locationStyles } = useLocation(props);
		const { positionClasses } = usePosition(props);
		const { roundedClasses } = useRounded(props);
		const link = useLink(props, attrs);
		useRender(() => {
			const isLink = props.link !== false && link.isLink.value;
			const isClickable = !props.disabled && props.link !== false && (props.link || link.isClickable.value);
			const Tag = isLink ? "a" : props.tag;
			const hasTitle = !!(slots.title || props.title != null);
			const hasSubtitle = !!(slots.subtitle || props.subtitle != null);
			const hasHeader = hasTitle || hasSubtitle;
			const hasAppend = !!(slots.append || props.appendAvatar || props.appendIcon);
			const hasPrepend = !!(slots.prepend || props.prependAvatar || props.prependIcon);
			const hasImage = !!(slots.image || props.image);
			const hasCardItem = hasHeader || hasPrepend || hasAppend;
			const hasText = !!(slots.text || props.text != null);
			return withDirectives(createVNode(Tag, mergeProps({
				"class": [
					"v-card",
					{
						"v-card--disabled": props.disabled,
						"v-card--flat": props.flat,
						"v-card--hover": props.hover && !(props.disabled || props.flat),
						"v-card--link": isClickable
					},
					themeClasses.value,
					borderClasses.value,
					colorClasses.value,
					densityClasses.value,
					elevationClasses.value,
					loaderClasses.value,
					positionClasses.value,
					roundedClasses.value,
					variantClasses.value,
					props.class
				],
				"style": [
					colorStyles.value,
					dimensionStyles.value,
					locationStyles.value,
					props.style
				],
				"onClick": isClickable && link.navigate,
				"tabindex": props.disabled ? -1 : void 0
			}, link.linkProps), { default: () => [
				hasImage && createVNode("div", {
					"key": "image",
					"class": "v-card__image"
				}, [!slots.image ? createVNode(VImg, {
					"key": "image-img",
					"cover": true,
					"src": props.image
				}, null) : createVNode(VDefaultsProvider, {
					"key": "image-defaults",
					"disabled": !props.image,
					"defaults": { VImg: {
						cover: true,
						src: props.image
					} }
				}, slots.image)]),
				createVNode(LoaderSlot, {
					"name": "v-card",
					"active": !!props.loading,
					"color": typeof props.loading === "boolean" ? void 0 : props.loading
				}, { default: slots.loader }),
				hasCardItem && createVNode(VCardItem, {
					"key": "item",
					"prependAvatar": props.prependAvatar,
					"prependIcon": props.prependIcon,
					"title": props.title,
					"subtitle": props.subtitle,
					"appendAvatar": props.appendAvatar,
					"appendIcon": props.appendIcon
				}, {
					default: slots.item,
					prepend: slots.prepend,
					title: slots.title,
					subtitle: slots.subtitle,
					append: slots.append
				}),
				hasText && createVNode(VCardText, { "key": "text" }, { default: () => [slots.text?.() ?? props.text] }),
				slots.default?.(),
				slots.actions && createVNode(VCardActions, null, { default: slots.actions }),
				genOverlays(isClickable, "v-card")
			] }), [[resolveDirective("ripple"), isClickable && props.ripple]]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/directives/touch/index.js
var handleGesture = (wrapper) => {
	const { touchstartX, touchendX, touchstartY, touchendY } = wrapper;
	const dirRatio = .5;
	const minDistance = 16;
	wrapper.offsetX = touchendX - touchstartX;
	wrapper.offsetY = touchendY - touchstartY;
	if (Math.abs(wrapper.offsetY) < dirRatio * Math.abs(wrapper.offsetX)) {
		wrapper.left && touchendX < touchstartX - minDistance && wrapper.left(wrapper);
		wrapper.right && touchendX > touchstartX + minDistance && wrapper.right(wrapper);
	}
	if (Math.abs(wrapper.offsetX) < dirRatio * Math.abs(wrapper.offsetY)) {
		wrapper.up && touchendY < touchstartY - minDistance && wrapper.up(wrapper);
		wrapper.down && touchendY > touchstartY + minDistance && wrapper.down(wrapper);
	}
};
function touchstart(event, wrapper) {
	const touch = event.changedTouches[0];
	wrapper.touchstartX = touch.clientX;
	wrapper.touchstartY = touch.clientY;
	wrapper.start?.({
		originalEvent: event,
		...wrapper
	});
}
function touchend(event, wrapper) {
	const touch = event.changedTouches[0];
	wrapper.touchendX = touch.clientX;
	wrapper.touchendY = touch.clientY;
	wrapper.end?.({
		originalEvent: event,
		...wrapper
	});
	handleGesture(wrapper);
}
function touchmove(event, wrapper) {
	const touch = event.changedTouches[0];
	wrapper.touchmoveX = touch.clientX;
	wrapper.touchmoveY = touch.clientY;
	wrapper.move?.({
		originalEvent: event,
		...wrapper
	});
}
function createHandlers() {
	let value = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : {};
	const wrapper = {
		touchstartX: 0,
		touchstartY: 0,
		touchendX: 0,
		touchendY: 0,
		touchmoveX: 0,
		touchmoveY: 0,
		offsetX: 0,
		offsetY: 0,
		left: value.left,
		right: value.right,
		up: value.up,
		down: value.down,
		start: value.start,
		move: value.move,
		end: value.end
	};
	return {
		touchstart: (e) => touchstart(e, wrapper),
		touchend: (e) => touchend(e, wrapper),
		touchmove: (e) => touchmove(e, wrapper)
	};
}
function mounted$3(el, binding) {
	const value = binding.value;
	const target = value?.parent ? el.parentElement : el;
	const options = value?.options ?? { passive: true };
	const uid = binding.instance?.$.uid;
	if (!target || !uid) return;
	const handlers = createHandlers(binding.value);
	target._touchHandlers = target._touchHandlers ?? Object.create(null);
	target._touchHandlers[uid] = handlers;
	keys(handlers).forEach((eventName) => {
		target.addEventListener(eventName, handlers[eventName], options);
	});
}
function unmounted$3(el, binding) {
	const target = binding.value?.parent ? el.parentElement : el;
	const uid = binding.instance?.$.uid;
	if (!target?._touchHandlers || !uid) return;
	const handlers = target._touchHandlers[uid];
	keys(handlers).forEach((eventName) => {
		target.removeEventListener(eventName, handlers[eventName]);
	});
	delete target._touchHandlers[uid];
}
var Touch = {
	mounted: mounted$3,
	unmounted: unmounted$3
};
//#endregion
//#region node_modules/vuetify/lib/components/VWindow/VWindow.js
var VWindowSymbol = Symbol.for("vuetify:v-window");
var VWindowGroupSymbol = Symbol.for("vuetify:v-window-group");
var makeVWindowProps = propsFactory({
	continuous: Boolean,
	nextIcon: {
		type: [
			Boolean,
			String,
			Function,
			Object
		],
		default: "$next"
	},
	prevIcon: {
		type: [
			Boolean,
			String,
			Function,
			Object
		],
		default: "$prev"
	},
	reverse: Boolean,
	showArrows: {
		type: [Boolean, String],
		validator: (v) => typeof v === "boolean" || v === "hover"
	},
	touch: {
		type: [Object, Boolean],
		default: void 0
	},
	direction: {
		type: String,
		default: "horizontal"
	},
	modelValue: null,
	disabled: Boolean,
	selectedClass: {
		type: String,
		default: "v-window-item--active"
	},
	mandatory: {
		type: [Boolean, String],
		default: "force"
	},
	...makeComponentProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VWindow");
var VWindow = genericComponent()({
	name: "VWindow",
	directives: { Touch },
	props: makeVWindowProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { isRtl } = useRtl();
		const { t } = useLocale();
		const group = useGroup(props, VWindowGroupSymbol);
		const rootRef = ref();
		const isRtlReverse = computed(() => isRtl.value ? !props.reverse : props.reverse);
		const isReversed = shallowRef(false);
		const transition = computed(() => {
			return `v-window-${props.direction === "vertical" ? "y" : "x"}${(isRtlReverse.value ? !isReversed.value : isReversed.value) ? "-reverse" : ""}-transition`;
		});
		const transitionCount = shallowRef(0);
		const transitionHeight = ref(void 0);
		const activeIndex = computed(() => {
			return group.items.value.findIndex((item) => group.selected.value.includes(item.id));
		});
		watch(activeIndex, (newVal, oldVal) => {
			const itemsLength = group.items.value.length;
			const lastIndex = itemsLength - 1;
			if (itemsLength <= 2) isReversed.value = newVal < oldVal;
			else if (newVal === lastIndex && oldVal === 0) isReversed.value = true;
			else if (newVal === 0 && oldVal === lastIndex) isReversed.value = false;
			else isReversed.value = newVal < oldVal;
		});
		provide(VWindowSymbol, {
			transition,
			isReversed,
			transitionCount,
			transitionHeight,
			rootRef
		});
		const canMoveBack = toRef(() => props.continuous || activeIndex.value !== 0);
		const canMoveForward = toRef(() => props.continuous || activeIndex.value !== group.items.value.length - 1);
		function prev() {
			canMoveBack.value && group.prev();
		}
		function next() {
			canMoveForward.value && group.next();
		}
		const arrows = computed(() => {
			const arrows = [];
			const prevProps = {
				icon: isRtl.value ? props.nextIcon : props.prevIcon,
				class: `v-window__${isRtlReverse.value ? "right" : "left"}`,
				onClick: group.prev,
				"aria-label": t("$vuetify.carousel.prev")
			};
			arrows.push(canMoveBack.value ? slots.prev ? slots.prev({ props: prevProps }) : createVNode(VBtn, prevProps, null) : createVNode("div", null, null));
			const nextProps = {
				icon: isRtl.value ? props.prevIcon : props.nextIcon,
				class: `v-window__${isRtlReverse.value ? "left" : "right"}`,
				onClick: group.next,
				"aria-label": t("$vuetify.carousel.next")
			};
			arrows.push(canMoveForward.value ? slots.next ? slots.next({ props: nextProps }) : createVNode(VBtn, nextProps, null) : createVNode("div", null, null));
			return arrows;
		});
		const touchOptions = computed(() => {
			if (props.touch === false) return props.touch;
			return {
				left: () => {
					isRtlReverse.value ? prev() : next();
				},
				right: () => {
					isRtlReverse.value ? next() : prev();
				},
				start: (_ref2) => {
					let { originalEvent } = _ref2;
					originalEvent.stopPropagation();
				},
				...props.touch === true ? {} : props.touch
			};
		});
		useRender(() => withDirectives(createVNode(props.tag, {
			"ref": rootRef,
			"class": [
				"v-window",
				{ "v-window--show-arrows-on-hover": props.showArrows === "hover" },
				themeClasses.value,
				props.class
			],
			"style": props.style
		}, { default: () => [createVNode("div", {
			"class": "v-window__container",
			"style": { height: transitionHeight.value }
		}, [slots.default?.({ group }), props.showArrows !== false && createVNode("div", { "class": "v-window__controls" }, [arrows.value])]), slots.additional?.({ group })] }), [[resolveDirective("touch"), touchOptions.value]]));
		return { group };
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCarousel/VCarousel.js
var makeVCarouselProps = propsFactory({
	color: String,
	cycle: Boolean,
	delimiterIcon: {
		type: IconValue,
		default: "$delimiter"
	},
	height: {
		type: [Number, String],
		default: 500
	},
	hideDelimiters: Boolean,
	hideDelimiterBackground: Boolean,
	interval: {
		type: [Number, String],
		default: 6e3,
		validator: (value) => Number(value) > 0
	},
	progress: [Boolean, String],
	verticalDelimiters: [Boolean, String],
	...makeVWindowProps({
		continuous: true,
		mandatory: "force",
		showArrows: true
	})
}, "VCarousel");
var VCarousel = genericComponent()({
	name: "VCarousel",
	props: makeVCarouselProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const { t } = useLocale();
		const windowRef = ref();
		let slideTimeout = -1;
		watch(model, restartTimeout);
		watch(() => props.interval, restartTimeout);
		watch(() => props.cycle, (val) => {
			if (val) restartTimeout();
			else window.clearTimeout(slideTimeout);
		});
		onMounted(startTimeout);
		function startTimeout() {
			if (!props.cycle || !windowRef.value) return;
			slideTimeout = window.setTimeout(windowRef.value.group.next, Number(props.interval) > 0 ? Number(props.interval) : 6e3);
		}
		function restartTimeout() {
			window.clearTimeout(slideTimeout);
			window.requestAnimationFrame(startTimeout);
		}
		useRender(() => {
			const windowProps = VWindow.filterProps(props);
			return createVNode(VWindow, mergeProps({ "ref": windowRef }, windowProps, {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"class": [
					"v-carousel",
					{
						"v-carousel--hide-delimiter-background": props.hideDelimiterBackground,
						"v-carousel--vertical-delimiters": props.verticalDelimiters
					},
					props.class
				],
				"style": [{ height: convertToUnit(props.height) }, props.style]
			}), {
				default: slots.default,
				additional: (_ref2) => {
					let { group } = _ref2;
					return createVNode(Fragment, null, [!props.hideDelimiters && createVNode("div", {
						"class": "v-carousel__controls",
						"style": {
							left: props.verticalDelimiters === "left" && props.verticalDelimiters ? 0 : "auto",
							right: props.verticalDelimiters === "right" ? 0 : "auto"
						}
					}, [group.items.value.length > 0 && createVNode(VDefaultsProvider, {
						"defaults": { VBtn: {
							color: props.color,
							icon: props.delimiterIcon,
							size: "x-small",
							variant: "text"
						} },
						"scoped": true
					}, { default: () => [group.items.value.map((item, index) => {
						const props = {
							id: `carousel-item-${item.id}`,
							"aria-label": t("$vuetify.carousel.ariaLabel.delimiter", index + 1, group.items.value.length),
							class: ["v-carousel__controls__item", group.isSelected(item.id) && "v-btn--active"],
							onClick: () => group.select(item.id, true)
						};
						return slots.item ? slots.item({
							props,
							item
						}) : createVNode(VBtn, mergeProps(item, props), null);
					})] })]), props.progress && createVNode(VProgressLinear, {
						"class": "v-carousel__progress",
						"color": typeof props.progress === "string" ? props.progress : void 0,
						"modelValue": (group.getItemIndex(model.value) + 1) / group.items.value.length * 100
					}, null)]);
				},
				prev: slots.prev,
				next: slots.next
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VWindow/VWindowItem.js
var makeVWindowItemProps = propsFactory({
	reverseTransition: {
		type: [Boolean, String],
		default: void 0
	},
	transition: {
		type: [Boolean, String],
		default: void 0
	},
	...makeComponentProps(),
	...makeGroupItemProps(),
	...makeLazyProps()
}, "VWindowItem");
var VWindowItem = genericComponent()({
	name: "VWindowItem",
	directives: { Touch },
	props: makeVWindowItemProps(),
	emits: { "group:selected": (val) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const window = inject(VWindowSymbol);
		const groupItem = useGroupItem(props, VWindowGroupSymbol);
		const { isBooted } = useSsrBoot();
		if (!window || !groupItem) throw new Error("[Vuetify] VWindowItem must be used inside VWindow");
		const isTransitioning = shallowRef(false);
		const hasTransition = computed(() => isBooted.value && (window.isReversed.value ? props.reverseTransition !== false : props.transition !== false));
		function onAfterTransition() {
			if (!isTransitioning.value || !window) return;
			isTransitioning.value = false;
			if (window.transitionCount.value > 0) {
				window.transitionCount.value -= 1;
				if (window.transitionCount.value === 0) window.transitionHeight.value = void 0;
			}
		}
		function onBeforeTransition() {
			if (isTransitioning.value || !window) return;
			isTransitioning.value = true;
			if (window.transitionCount.value === 0) window.transitionHeight.value = convertToUnit(window.rootRef.value?.clientHeight);
			window.transitionCount.value += 1;
		}
		function onTransitionCancelled() {
			onAfterTransition();
		}
		function onEnterTransition(el) {
			if (!isTransitioning.value) return;
			nextTick(() => {
				if (!hasTransition.value || !isTransitioning.value || !window) return;
				window.transitionHeight.value = convertToUnit(el.clientHeight);
			});
		}
		const transition = computed(() => {
			const name = window.isReversed.value ? props.reverseTransition : props.transition;
			return !hasTransition.value ? false : {
				name: typeof name !== "string" ? window.transition.value : name,
				onBeforeEnter: onBeforeTransition,
				onAfterEnter: onAfterTransition,
				onEnterCancelled: onTransitionCancelled,
				onBeforeLeave: onBeforeTransition,
				onAfterLeave: onAfterTransition,
				onLeaveCancelled: onTransitionCancelled,
				onEnter: onEnterTransition
			};
		});
		const { hasContent } = useLazy(props, groupItem.isSelected);
		useRender(() => createVNode(MaybeTransition, {
			"transition": transition.value,
			"disabled": !isBooted.value
		}, { default: () => [withDirectives(createVNode("div", {
			"class": [
				"v-window-item",
				groupItem.selectedClass.value,
				props.class
			],
			"style": props.style
		}, [hasContent.value && slots.default?.()]), [[vShow, groupItem.isSelected.value]])] }));
		return { groupItem };
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCarousel/VCarouselItem.js
var makeVCarouselItemProps = propsFactory({
	...makeVImgProps(),
	...makeVWindowItemProps()
}, "VCarouselItem");
var VCarouselItem = genericComponent()({
	name: "VCarouselItem",
	inheritAttrs: false,
	props: makeVCarouselItemProps(),
	setup(props, _ref) {
		let { slots, attrs } = _ref;
		useRender(() => {
			const imgProps = VImg.filterProps(props);
			const windowItemProps = VWindowItem.filterProps(props);
			return createVNode(VWindowItem, mergeProps({ "class": ["v-carousel-item", props.class] }, windowItemProps), { default: () => [createVNode(VImg, mergeProps(attrs, imgProps), slots)] });
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCode/index.js
var VCode = createSimpleFunctional("v-code", "code");
var VColorPickerCanvas = defineComponent$1({
	name: "VColorPickerCanvas",
	props: propsFactory({
		color: { type: Object },
		disabled: Boolean,
		dotSize: {
			type: [Number, String],
			default: 10
		},
		height: {
			type: [Number, String],
			default: 150
		},
		width: {
			type: [Number, String],
			default: 300
		},
		...makeComponentProps()
	}, "VColorPickerCanvas")(),
	emits: {
		"update:color": (color) => true,
		"update:position": (hue) => true
	},
	setup(props, _ref) {
		let { emit } = _ref;
		const isInteracting = shallowRef(false);
		const canvasRef = ref();
		const canvasWidth = shallowRef(parseFloat(props.width));
		const canvasHeight = shallowRef(parseFloat(props.height));
		const _dotPosition = ref({
			x: 0,
			y: 0
		});
		const dotPosition = computed({
			get: () => _dotPosition.value,
			set(val) {
				if (!canvasRef.value) return;
				const { x, y } = val;
				_dotPosition.value = val;
				emit("update:color", {
					h: props.color?.h ?? 0,
					s: clamp(x, 0, canvasWidth.value) / canvasWidth.value,
					v: 1 - clamp(y, 0, canvasHeight.value) / canvasHeight.value,
					a: props.color?.a ?? 1
				});
			}
		});
		const dotStyles = computed(() => {
			const { x, y } = dotPosition.value;
			const radius = parseInt(props.dotSize, 10) / 2;
			return {
				width: convertToUnit(props.dotSize),
				height: convertToUnit(props.dotSize),
				transform: `translate(${convertToUnit(x - radius)}, ${convertToUnit(y - radius)})`
			};
		});
		const { resizeRef } = useResizeObserver((entries) => {
			if (!resizeRef.el?.offsetParent) return;
			const { width, height } = entries[0].contentRect;
			canvasWidth.value = width;
			canvasHeight.value = height;
		});
		function updateDotPosition(x, y, rect) {
			const { left, top, width, height } = rect;
			dotPosition.value = {
				x: clamp(x - left, 0, width),
				y: clamp(y - top, 0, height)
			};
		}
		function handleMouseDown(e) {
			if (e.type === "mousedown") e.preventDefault();
			if (props.disabled) return;
			handleMouseMove(e);
			window.addEventListener("mousemove", handleMouseMove);
			window.addEventListener("mouseup", handleMouseUp);
			window.addEventListener("touchmove", handleMouseMove);
			window.addEventListener("touchend", handleMouseUp);
		}
		function handleMouseMove(e) {
			if (props.disabled || !canvasRef.value) return;
			isInteracting.value = true;
			const coords = getEventCoordinates(e);
			updateDotPosition(coords.clientX, coords.clientY, canvasRef.value.getBoundingClientRect());
		}
		function handleMouseUp() {
			window.removeEventListener("mousemove", handleMouseMove);
			window.removeEventListener("mouseup", handleMouseUp);
			window.removeEventListener("touchmove", handleMouseMove);
			window.removeEventListener("touchend", handleMouseUp);
		}
		function updateCanvas() {
			if (!canvasRef.value) return;
			const canvas = canvasRef.value;
			const ctx = canvas.getContext("2d");
			if (!ctx) return;
			const saturationGradient = ctx.createLinearGradient(0, 0, canvas.width, 0);
			saturationGradient.addColorStop(0, "hsla(0, 0%, 100%, 1)");
			saturationGradient.addColorStop(1, `hsla(${props.color?.h ?? 0}, 100%, 50%, 1)`);
			ctx.fillStyle = saturationGradient;
			ctx.fillRect(0, 0, canvas.width, canvas.height);
			const valueGradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
			valueGradient.addColorStop(0, "hsla(0, 0%, 0%, 0)");
			valueGradient.addColorStop(1, "hsla(0, 0%, 0%, 1)");
			ctx.fillStyle = valueGradient;
			ctx.fillRect(0, 0, canvas.width, canvas.height);
		}
		watch(() => props.color?.h, updateCanvas, { immediate: true });
		watch(() => [canvasWidth.value, canvasHeight.value], (newVal, oldVal) => {
			updateCanvas();
			_dotPosition.value = {
				x: dotPosition.value.x * newVal[0] / oldVal[0],
				y: dotPosition.value.y * newVal[1] / oldVal[1]
			};
		}, { flush: "post" });
		watch(() => props.color, () => {
			if (isInteracting.value) {
				isInteracting.value = false;
				return;
			}
			_dotPosition.value = props.color ? {
				x: props.color.s * canvasWidth.value,
				y: (1 - props.color.v) * canvasHeight.value
			} : {
				x: 0,
				y: 0
			};
		}, {
			deep: true,
			immediate: true
		});
		onMounted(() => updateCanvas());
		useRender(() => createVNode("div", {
			"ref": resizeRef,
			"class": ["v-color-picker-canvas", props.class],
			"style": props.style,
			"onMousedown": handleMouseDown,
			"onTouchstartPassive": handleMouseDown
		}, [createVNode("canvas", {
			"ref": canvasRef,
			"width": canvasWidth.value,
			"height": canvasHeight.value
		}, null), props.color && createVNode("div", {
			"class": ["v-color-picker-canvas__dot", { "v-color-picker-canvas__dot--disabled": props.disabled }],
			"style": dotStyles.value
		}, null)]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VColorPicker/util/index.js
function stripAlpha(color, stripAlpha) {
	if (stripAlpha) {
		const { a, ...rest } = color;
		return rest;
	}
	return color;
}
function extractColor(color, input) {
	if (input == null || typeof input === "string") {
		const hasA = color.a !== 1;
		if (input?.startsWith("rgb(")) {
			const { r, g, b, a } = HSVtoRGB(color);
			return `rgb(${r} ${g} ${b}` + (hasA ? ` / ${a})` : ")");
		} else if (input?.startsWith("hsl(")) {
			const { h, s, l, a } = HSVtoHSL(color);
			return `hsl(${h} ${Math.round(s * 100)} ${Math.round(l * 100)}` + (hasA ? ` / ${a})` : ")");
		}
		const hex = HSVtoHex(color);
		if (color.a === 1) return hex.slice(0, 7);
		else return hex;
	}
	if (typeof input === "object") {
		let converted;
		if (has(input, [
			"r",
			"g",
			"b"
		])) converted = HSVtoRGB(color);
		else if (has(input, [
			"h",
			"s",
			"l"
		])) converted = HSVtoHSL(color);
		else if (has(input, [
			"h",
			"s",
			"v"
		])) converted = color;
		return stripAlpha(converted, !has(input, ["a"]) && color.a === 1);
	}
	return color;
}
var nullColor = {
	h: 0,
	s: 0,
	v: 0,
	a: 1
};
var rgba = {
	inputProps: {
		type: "number",
		min: 0
	},
	inputs: [
		{
			label: "R",
			max: 255,
			step: 1,
			getValue: (c) => Math.round(c.r),
			getColor: (c, v) => ({
				...c,
				r: Number(v)
			})
		},
		{
			label: "G",
			max: 255,
			step: 1,
			getValue: (c) => Math.round(c.g),
			getColor: (c, v) => ({
				...c,
				g: Number(v)
			})
		},
		{
			label: "B",
			max: 255,
			step: 1,
			getValue: (c) => Math.round(c.b),
			getColor: (c, v) => ({
				...c,
				b: Number(v)
			})
		},
		{
			label: "A",
			max: 1,
			step: .01,
			getValue: (_ref) => {
				let { a } = _ref;
				return a != null ? Math.round(a * 100) / 100 : 1;
			},
			getColor: (c, v) => ({
				...c,
				a: Number(v)
			})
		}
	],
	to: HSVtoRGB,
	from: RGBtoHSV
};
var rgb = {
	...rgba,
	inputs: rgba.inputs?.slice(0, 3)
};
var hsla = {
	inputProps: {
		type: "number",
		min: 0
	},
	inputs: [
		{
			label: "H",
			max: 360,
			step: 1,
			getValue: (c) => Math.round(c.h),
			getColor: (c, v) => ({
				...c,
				h: Number(v)
			})
		},
		{
			label: "S",
			max: 1,
			step: .01,
			getValue: (c) => Math.round(c.s * 100) / 100,
			getColor: (c, v) => ({
				...c,
				s: Number(v)
			})
		},
		{
			label: "L",
			max: 1,
			step: .01,
			getValue: (c) => Math.round(c.l * 100) / 100,
			getColor: (c, v) => ({
				...c,
				l: Number(v)
			})
		},
		{
			label: "A",
			max: 1,
			step: .01,
			getValue: (_ref2) => {
				let { a } = _ref2;
				return a != null ? Math.round(a * 100) / 100 : 1;
			},
			getColor: (c, v) => ({
				...c,
				a: Number(v)
			})
		}
	],
	to: HSVtoHSL,
	from: HSLtoHSV
};
var hsl = {
	...hsla,
	inputs: hsla.inputs.slice(0, 3)
};
var hexa = {
	inputProps: { type: "text" },
	inputs: [{
		label: "HEXA",
		getValue: (c) => c,
		getColor: (c, v) => v
	}],
	to: HSVtoHex,
	from: HexToHSV
};
var modes = {
	rgb,
	rgba,
	hsl,
	hsla,
	hex: {
		...hexa,
		inputs: [{
			label: "HEX",
			getValue: (c) => c.slice(0, 7),
			getColor: (c, v) => v
		}]
	},
	hexa
};
//#endregion
//#region node_modules/vuetify/lib/components/VColorPicker/VColorPickerEdit.js
var VColorPickerInput = (_ref) => {
	let { label, ...rest } = _ref;
	return createVNode("div", { "class": "v-color-picker-edit__input" }, [createVNode("input", rest, null), createVNode("span", null, [label])]);
};
var VColorPickerEdit = defineComponent$1({
	name: "VColorPickerEdit",
	props: propsFactory({
		color: Object,
		disabled: Boolean,
		mode: {
			type: String,
			default: "rgba",
			validator: (v) => Object.keys(modes).includes(v)
		},
		modes: {
			type: Array,
			default: () => Object.keys(modes),
			validator: (v) => Array.isArray(v) && v.every((m) => Object.keys(modes).includes(m))
		},
		...makeComponentProps()
	}, "VColorPickerEdit")(),
	emits: {
		"update:color": (color) => true,
		"update:mode": (mode) => true
	},
	setup(props, _ref2) {
		let { emit } = _ref2;
		const enabledModes = computed(() => {
			return props.modes.map((key) => ({
				...modes[key],
				name: key
			}));
		});
		const inputs = computed(() => {
			const mode = enabledModes.value.find((m) => m.name === props.mode);
			if (!mode) return [];
			const color = props.color ? mode.to(props.color) : null;
			return mode.inputs?.map((_ref3) => {
				let { getValue, getColor, ...inputProps } = _ref3;
				return {
					...mode.inputProps,
					...inputProps,
					disabled: props.disabled,
					value: color && getValue(color),
					onChange: (e) => {
						const target = e.target;
						if (!target) return;
						emit("update:color", mode.from(getColor(color ?? mode.to(nullColor), target.value)));
					}
				};
			});
		});
		useRender(() => createVNode("div", {
			"class": ["v-color-picker-edit", props.class],
			"style": props.style
		}, [inputs.value?.map((props) => createVNode(VColorPickerInput, props, null)), enabledModes.value.length > 1 && createVNode(VBtn, {
			"icon": "$unfold",
			"size": "x-small",
			"variant": "plain",
			"onClick": () => {
				const mi = enabledModes.value.findIndex((m) => m.name === props.mode);
				emit("update:mode", enabledModes.value[(mi + 1) % enabledModes.value.length].name);
			}
		}, null)]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSlider/slider.js
var VSliderSymbol = Symbol.for("vuetify:v-slider");
function getOffset(e, el, direction) {
	const vertical = direction === "vertical";
	const rect = el.getBoundingClientRect();
	const touch = "touches" in e ? e.touches[0] : e;
	return vertical ? touch.clientY - (rect.top + rect.height / 2) : touch.clientX - (rect.left + rect.width / 2);
}
function getPosition(e, position) {
	if ("touches" in e && e.touches.length) return e.touches[0][position];
	else if ("changedTouches" in e && e.changedTouches.length) return e.changedTouches[0][position];
	else return e[position];
}
var makeSliderProps = propsFactory({
	disabled: {
		type: Boolean,
		default: null
	},
	error: Boolean,
	readonly: {
		type: Boolean,
		default: null
	},
	max: {
		type: [Number, String],
		default: 100
	},
	min: {
		type: [Number, String],
		default: 0
	},
	step: {
		type: [Number, String],
		default: 0
	},
	thumbColor: String,
	thumbLabel: {
		type: [Boolean, String],
		default: void 0,
		validator: (v) => typeof v === "boolean" || v === "always"
	},
	thumbSize: {
		type: [Number, String],
		default: 20
	},
	showTicks: {
		type: [Boolean, String],
		default: false,
		validator: (v) => typeof v === "boolean" || v === "always"
	},
	ticks: { type: [Array, Object] },
	tickSize: {
		type: [Number, String],
		default: 2
	},
	color: String,
	trackColor: String,
	trackFillColor: String,
	trackSize: {
		type: [Number, String],
		default: 4
	},
	direction: {
		type: String,
		default: "horizontal",
		validator: (v) => ["vertical", "horizontal"].includes(v)
	},
	reverse: Boolean,
	...makeRoundedProps(),
	...makeElevationProps({ elevation: 2 }),
	ripple: {
		type: Boolean,
		default: true
	}
}, "Slider");
var useSteps = (props) => {
	const min = computed(() => parseFloat(props.min));
	const max = computed(() => parseFloat(props.max));
	const step = computed(() => Number(props.step) > 0 ? parseFloat(props.step) : 0);
	const decimals = computed(() => Math.max(getDecimals(step.value), getDecimals(min.value)));
	function roundValue(value) {
		value = parseFloat(value);
		if (step.value <= 0) return value;
		const clamped = clamp(value, min.value, max.value);
		const offset = min.value % step.value;
		const newValue = Math.round((clamped - offset) / step.value) * step.value + offset;
		return parseFloat(Math.min(newValue, max.value).toFixed(decimals.value));
	}
	return {
		min,
		max,
		step,
		decimals,
		roundValue
	};
};
var useSlider = (_ref) => {
	let { props, steps, onSliderStart, onSliderMove, onSliderEnd, getActiveThumb } = _ref;
	const { isRtl } = useRtl();
	const isReversed = toRef(() => props.reverse);
	const vertical = computed(() => props.direction === "vertical");
	const indexFromEnd = computed(() => vertical.value !== isReversed.value);
	const { min, max, step, decimals, roundValue } = steps;
	const thumbSize = computed(() => parseInt(props.thumbSize, 10));
	const tickSize = computed(() => parseInt(props.tickSize, 10));
	const trackSize = computed(() => parseInt(props.trackSize, 10));
	const numTicks = computed(() => (max.value - min.value) / step.value);
	const disabled = toRef(() => props.disabled);
	const thumbColor = computed(() => props.error || props.disabled ? void 0 : props.thumbColor ?? props.color);
	const trackColor = computed(() => props.error || props.disabled ? void 0 : props.trackColor ?? props.color);
	const trackFillColor = computed(() => props.error || props.disabled ? void 0 : props.trackFillColor ?? props.color);
	const mousePressed = shallowRef(false);
	const startOffset = shallowRef(0);
	const trackContainerRef = ref();
	const activeThumbRef = ref();
	function parseMouseMove(e) {
		const el = trackContainerRef.value?.$el;
		if (!el) return;
		const vertical = props.direction === "vertical";
		const start = vertical ? "top" : "left";
		const length = vertical ? "height" : "width";
		const position = vertical ? "clientY" : "clientX";
		const { [start]: trackStart, [length]: trackLength } = el.getBoundingClientRect();
		const clickOffset = getPosition(e, position);
		let clickPos = Math.min(Math.max((clickOffset - trackStart - startOffset.value) / trackLength, 0), 1) || 0;
		if (vertical ? indexFromEnd.value : indexFromEnd.value !== isRtl.value) clickPos = 1 - clickPos;
		return roundValue(min.value + clickPos * (max.value - min.value));
	}
	const handleStop = (e) => {
		const value = parseMouseMove(e);
		if (value != null) onSliderEnd({ value });
		mousePressed.value = false;
		startOffset.value = 0;
	};
	const handleStart = (e) => {
		const value = parseMouseMove(e);
		activeThumbRef.value = getActiveThumb(e);
		if (!activeThumbRef.value) return;
		mousePressed.value = true;
		if (activeThumbRef.value.contains(e.target)) startOffset.value = getOffset(e, activeThumbRef.value, props.direction);
		else {
			startOffset.value = 0;
			if (value != null) onSliderMove({ value });
		}
		if (value != null) onSliderStart({ value });
		nextTick(() => activeThumbRef.value?.focus());
	};
	const moveListenerOptions = {
		passive: true,
		capture: true
	};
	function onMouseMove(e) {
		const value = parseMouseMove(e);
		if (value != null) onSliderMove({ value });
	}
	function onSliderMouseUp(e) {
		e.stopPropagation();
		e.preventDefault();
		handleStop(e);
		window.removeEventListener("mousemove", onMouseMove, moveListenerOptions);
		window.removeEventListener("mouseup", onSliderMouseUp);
	}
	function onSliderTouchend(e) {
		handleStop(e);
		window.removeEventListener("touchmove", onMouseMove, moveListenerOptions);
		e.target?.removeEventListener("touchend", onSliderTouchend);
	}
	function onSliderTouchstart(e) {
		handleStart(e);
		window.addEventListener("touchmove", onMouseMove, moveListenerOptions);
		e.target?.addEventListener("touchend", onSliderTouchend, { passive: false });
	}
	function onSliderMousedown(e) {
		if (e.button !== 0) return;
		e.preventDefault();
		handleStart(e);
		window.addEventListener("mousemove", onMouseMove, moveListenerOptions);
		window.addEventListener("mouseup", onSliderMouseUp, { passive: false });
	}
	const position = (val) => {
		const percentage = (val - min.value) / (max.value - min.value) * 100;
		return clamp(isNaN(percentage) ? 0 : percentage, 0, 100);
	};
	const showTicks = toRef(() => props.showTicks);
	const parsedTicks = computed(() => {
		if (!showTicks.value) return [];
		if (!props.ticks) return numTicks.value !== Infinity ? createRange(numTicks.value + 1).map((t) => {
			const value = min.value + t * step.value;
			return {
				value,
				position: position(value)
			};
		}) : [];
		if (Array.isArray(props.ticks)) return props.ticks.map((t) => ({
			value: t,
			position: position(t),
			label: t.toString()
		}));
		return Object.keys(props.ticks).map((key) => ({
			value: parseFloat(key),
			position: position(parseFloat(key)),
			label: props.ticks[key]
		}));
	});
	const hasLabels = computed(() => parsedTicks.value.some((_ref2) => {
		let { label } = _ref2;
		return !!label;
	}));
	const data = {
		activeThumbRef,
		color: toRef(() => props.color),
		decimals,
		disabled,
		direction: toRef(() => props.direction),
		elevation: toRef(() => props.elevation),
		hasLabels,
		isReversed,
		indexFromEnd,
		min,
		max,
		mousePressed,
		numTicks,
		onSliderMousedown,
		onSliderTouchstart,
		parsedTicks,
		parseMouseMove,
		position,
		readonly: toRef(() => props.readonly),
		rounded: toRef(() => props.rounded),
		roundValue,
		showTicks,
		startOffset,
		step,
		thumbSize,
		thumbColor,
		thumbLabel: toRef(() => props.thumbLabel),
		ticks: toRef(() => props.ticks),
		tickSize,
		trackColor,
		trackContainerRef,
		trackFillColor,
		trackSize,
		vertical
	};
	provide(VSliderSymbol, data);
	return data;
};
//#endregion
//#region node_modules/vuetify/lib/components/VSlider/VSliderThumb.js
var makeVSliderThumbProps = propsFactory({
	focused: Boolean,
	max: {
		type: Number,
		required: true
	},
	min: {
		type: Number,
		required: true
	},
	modelValue: {
		type: Number,
		required: true
	},
	position: {
		type: Number,
		required: true
	},
	ripple: {
		type: [Boolean, Object],
		default: true
	},
	name: String,
	...makeComponentProps()
}, "VSliderThumb");
var VSliderThumb = genericComponent()({
	name: "VSliderThumb",
	directives: { Ripple },
	props: makeVSliderThumbProps(),
	emits: { "update:modelValue": (v) => true },
	setup(props, _ref) {
		let { slots, emit } = _ref;
		const slider = inject(VSliderSymbol);
		const { isRtl, rtlClasses } = useRtl();
		if (!slider) throw new Error("[Vuetify] v-slider-thumb must be used inside v-slider or v-range-slider");
		const { thumbColor, step, disabled, thumbSize, thumbLabel, direction, isReversed, vertical, readonly, elevation, mousePressed, decimals, indexFromEnd } = slider;
		const { elevationClasses } = useElevation(computed(() => !disabled.value ? elevation.value : void 0));
		const { textColorClasses, textColorStyles } = useTextColor(thumbColor);
		const { pageup, pagedown, end, home, left, right, down, up } = keyValues;
		const relevantKeys = [
			pageup,
			pagedown,
			end,
			home,
			left,
			right,
			down,
			up
		];
		const multipliers = computed(() => {
			if (step.value) return [
				1,
				2,
				3
			];
			else return [
				1,
				5,
				10
			];
		});
		function parseKeydown(e, value) {
			if (!relevantKeys.includes(e.key)) return;
			e.preventDefault();
			const _step = step.value || .1;
			const steps = (props.max - props.min) / _step;
			if ([
				left,
				right,
				down,
				up
			].includes(e.key)) {
				const direction = (vertical.value ? [isRtl.value ? left : right, isReversed.value ? down : up] : indexFromEnd.value !== isRtl.value ? [left, up] : [right, up]).includes(e.key) ? 1 : -1;
				const multiplier = e.shiftKey ? 2 : e.ctrlKey ? 1 : 0;
				value = value + direction * _step * multipliers.value[multiplier];
			} else if (e.key === home) value = props.min;
			else if (e.key === end) value = props.max;
			else {
				const direction = e.key === pagedown ? 1 : -1;
				value = value - direction * _step * (steps > 100 ? steps / 10 : 10);
			}
			return Math.max(props.min, Math.min(props.max, value));
		}
		function onKeydown(e) {
			const newValue = parseKeydown(e, props.modelValue);
			newValue != null && emit("update:modelValue", newValue);
		}
		useRender(() => {
			const positionPercentage = convertToUnit(indexFromEnd.value ? 100 - props.position : props.position, "%");
			return createVNode("div", {
				"class": [
					"v-slider-thumb",
					{
						"v-slider-thumb--focused": props.focused,
						"v-slider-thumb--pressed": props.focused && mousePressed.value
					},
					props.class,
					rtlClasses.value
				],
				"style": [{
					"--v-slider-thumb-position": positionPercentage,
					"--v-slider-thumb-size": convertToUnit(thumbSize.value)
				}, props.style],
				"role": "slider",
				"tabindex": disabled.value ? -1 : 0,
				"aria-label": props.name,
				"aria-valuemin": props.min,
				"aria-valuemax": props.max,
				"aria-valuenow": props.modelValue,
				"aria-readonly": !!readonly.value,
				"aria-orientation": direction.value,
				"onKeydown": !readonly.value ? onKeydown : void 0
			}, [
				createVNode("div", {
					"class": [
						"v-slider-thumb__surface",
						textColorClasses.value,
						elevationClasses.value
					],
					"style": { ...textColorStyles.value }
				}, null),
				withDirectives(createVNode("div", {
					"class": ["v-slider-thumb__ripple", textColorClasses.value],
					"style": textColorStyles.value
				}, null), [[
					resolveDirective("ripple"),
					props.ripple,
					null,
					{
						circle: true,
						center: true
					}
				]]),
				createVNode(VScaleTransition, { "origin": "bottom center" }, { default: () => [withDirectives(createVNode("div", { "class": "v-slider-thumb__label-container" }, [createVNode("div", { "class": ["v-slider-thumb__label"] }, [createVNode("div", null, [slots["thumb-label"]?.({ modelValue: props.modelValue }) ?? props.modelValue.toFixed(step.value ? decimals.value : 1)])])]), [[vShow, thumbLabel.value && props.focused || thumbLabel.value === "always"]])] })
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSlider/VSliderTrack.js
var makeVSliderTrackProps = propsFactory({
	start: {
		type: Number,
		required: true
	},
	stop: {
		type: Number,
		required: true
	},
	...makeComponentProps()
}, "VSliderTrack");
var VSliderTrack = genericComponent()({
	name: "VSliderTrack",
	props: makeVSliderTrackProps(),
	emits: {},
	setup(props, _ref) {
		let { slots } = _ref;
		const slider = inject(VSliderSymbol);
		if (!slider) throw new Error("[Vuetify] v-slider-track must be inside v-slider or v-range-slider");
		const { color, parsedTicks, rounded, showTicks, tickSize, trackColor, trackFillColor, trackSize, vertical, min, max, indexFromEnd } = slider;
		const { roundedClasses } = useRounded(rounded);
		const { backgroundColorClasses: trackFillColorClasses, backgroundColorStyles: trackFillColorStyles } = useBackgroundColor(trackFillColor);
		const { backgroundColorClasses: trackColorClasses, backgroundColorStyles: trackColorStyles } = useBackgroundColor(trackColor);
		const startDir = computed(() => `inset-${vertical.value ? "block" : "inline"}-${indexFromEnd.value ? "end" : "start"}`);
		const endDir = computed(() => vertical.value ? "height" : "width");
		const backgroundStyles = computed(() => {
			return {
				[startDir.value]: "0%",
				[endDir.value]: "100%"
			};
		});
		const trackFillWidth = computed(() => props.stop - props.start);
		const trackFillStyles = computed(() => {
			return {
				[startDir.value]: convertToUnit(props.start, "%"),
				[endDir.value]: convertToUnit(trackFillWidth.value, "%")
			};
		});
		const computedTicks = computed(() => {
			if (!showTicks.value) return [];
			return (vertical.value ? parsedTicks.value.slice().reverse() : parsedTicks.value).map((tick, index) => {
				const directionValue = tick.value !== min.value && tick.value !== max.value ? convertToUnit(tick.position, "%") : void 0;
				return createVNode("div", {
					"key": tick.value,
					"class": ["v-slider-track__tick", {
						"v-slider-track__tick--filled": tick.position >= props.start && tick.position <= props.stop,
						"v-slider-track__tick--first": tick.value === min.value,
						"v-slider-track__tick--last": tick.value === max.value
					}],
					"style": { [startDir.value]: directionValue }
				}, [(tick.label || slots["tick-label"]) && createVNode("div", { "class": "v-slider-track__tick-label" }, [slots["tick-label"]?.({
					tick,
					index
				}) ?? tick.label])]);
			});
		});
		useRender(() => {
			return createVNode("div", {
				"class": [
					"v-slider-track",
					roundedClasses.value,
					props.class
				],
				"style": [{
					"--v-slider-track-size": convertToUnit(trackSize.value),
					"--v-slider-tick-size": convertToUnit(tickSize.value)
				}, props.style]
			}, [
				createVNode("div", {
					"class": [
						"v-slider-track__background",
						trackColorClasses.value,
						{ "v-slider-track__background--opacity": !!color.value || !trackFillColor.value }
					],
					"style": {
						...backgroundStyles.value,
						...trackColorStyles.value
					}
				}, null),
				createVNode("div", {
					"class": ["v-slider-track__fill", trackFillColorClasses.value],
					"style": {
						...trackFillStyles.value,
						...trackFillColorStyles.value
					}
				}, null),
				showTicks.value && createVNode("div", { "class": ["v-slider-track__ticks", { "v-slider-track__ticks--always-show": showTicks.value === "always" }] }, [computedTicks.value])
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSlider/VSlider.js
var makeVSliderProps = propsFactory({
	...makeFocusProps(),
	...makeSliderProps(),
	...makeVInputProps(),
	modelValue: {
		type: [Number, String],
		default: 0
	}
}, "VSlider");
var VSlider = genericComponent()({
	name: "VSlider",
	props: makeVSliderProps(),
	emits: {
		"update:focused": (value) => true,
		"update:modelValue": (v) => true,
		start: (value) => true,
		end: (value) => true
	},
	setup(props, _ref) {
		let { slots, emit } = _ref;
		const thumbContainerRef = ref();
		const { rtlClasses } = useRtl();
		const steps = useSteps(props);
		const model = useProxiedModel(props, "modelValue", void 0, (value) => {
			return steps.roundValue(value == null ? steps.min.value : value);
		});
		const { min, max, mousePressed, roundValue, onSliderMousedown, onSliderTouchstart, trackContainerRef, position, hasLabels, readonly } = useSlider({
			props,
			steps,
			onSliderStart: () => {
				emit("start", model.value);
			},
			onSliderEnd: (_ref2) => {
				let { value } = _ref2;
				const roundedValue = roundValue(value);
				model.value = roundedValue;
				emit("end", roundedValue);
			},
			onSliderMove: (_ref3) => {
				let { value } = _ref3;
				return model.value = roundValue(value);
			},
			getActiveThumb: () => thumbContainerRef.value?.$el
		});
		const { isFocused, focus, blur } = useFocus(props);
		const trackStop = computed(() => position(model.value));
		useRender(() => {
			const inputProps = VInput.filterProps(props);
			const hasPrepend = !!(props.label || slots.label || slots.prepend);
			return createVNode(VInput, mergeProps({
				"class": [
					"v-slider",
					{
						"v-slider--has-labels": !!slots["tick-label"] || hasLabels.value,
						"v-slider--focused": isFocused.value,
						"v-slider--pressed": mousePressed.value,
						"v-slider--disabled": props.disabled
					},
					rtlClasses.value,
					props.class
				],
				"style": props.style
			}, inputProps, { "focused": isFocused.value }), {
				...slots,
				prepend: hasPrepend ? (slotProps) => createVNode(Fragment, null, [slots.label?.(slotProps) ?? (props.label ? createVNode(VLabel, {
					"id": slotProps.id.value,
					"class": "v-slider__label",
					"text": props.label
				}, null) : void 0), slots.prepend?.(slotProps)]) : void 0,
				default: (_ref4) => {
					let { id, messagesId } = _ref4;
					return createVNode("div", {
						"class": "v-slider__container",
						"onMousedown": !readonly.value ? onSliderMousedown : void 0,
						"onTouchstartPassive": !readonly.value ? onSliderTouchstart : void 0
					}, [
						createVNode("input", {
							"id": id.value,
							"name": props.name || id.value,
							"disabled": !!props.disabled,
							"readonly": !!props.readonly,
							"tabindex": "-1",
							"value": model.value
						}, null),
						createVNode(VSliderTrack, {
							"ref": trackContainerRef,
							"start": 0,
							"stop": trackStop.value
						}, { "tick-label": slots["tick-label"] }),
						createVNode(VSliderThumb, {
							"ref": thumbContainerRef,
							"aria-describedby": messagesId.value,
							"focused": isFocused.value,
							"min": min.value,
							"max": max.value,
							"modelValue": model.value,
							"onUpdate:modelValue": (v) => model.value = v,
							"position": trackStop.value,
							"elevation": props.elevation,
							"onFocus": focus,
							"onBlur": blur,
							"ripple": props.ripple,
							"name": props.name
						}, { "thumb-label": slots["thumb-label"] })
					]);
				}
			});
		});
		return {};
	}
});
var VColorPickerPreview = defineComponent$1({
	name: "VColorPickerPreview",
	props: propsFactory({
		color: { type: Object },
		disabled: Boolean,
		hideAlpha: Boolean,
		...makeComponentProps()
	}, "VColorPickerPreview")(),
	emits: { "update:color": (color) => true },
	setup(props, _ref) {
		let { emit } = _ref;
		const abortController = new AbortController();
		onUnmounted(() => abortController.abort());
		async function openEyeDropper() {
			if (!SUPPORTS_EYE_DROPPER || props.disabled) return;
			const eyeDropper = new window.EyeDropper();
			try {
				const colorHexValue = RGBtoHSV(parseColor((await eyeDropper.open({ signal: abortController.signal })).sRGBHex));
				emit("update:color", {
					...props.color ?? nullColor,
					...colorHexValue
				});
			} catch (e) {}
		}
		useRender(() => createVNode("div", {
			"class": [
				"v-color-picker-preview",
				{ "v-color-picker-preview--hide-alpha": props.hideAlpha },
				props.class
			],
			"style": props.style
		}, [
			SUPPORTS_EYE_DROPPER && createVNode("div", {
				"class": "v-color-picker-preview__eye-dropper",
				"key": "eyeDropper"
			}, [createVNode(VBtn, {
				"density": "comfortable",
				"disabled": props.disabled,
				"icon": "$eyeDropper",
				"variant": "plain",
				"onClick": openEyeDropper
			}, null)]),
			createVNode("div", { "class": "v-color-picker-preview__dot" }, [createVNode("div", { "style": { background: HSVtoCSS(props.color ?? nullColor) } }, null)]),
			createVNode("div", { "class": "v-color-picker-preview__sliders" }, [createVNode(VSlider, {
				"class": "v-color-picker-preview__track v-color-picker-preview__hue",
				"modelValue": props.color?.h,
				"onUpdate:modelValue": (h) => emit("update:color", {
					...props.color ?? nullColor,
					h
				}),
				"step": 0,
				"min": 0,
				"max": 360,
				"disabled": props.disabled,
				"thumbSize": 14,
				"trackSize": 8,
				"trackFillColor": "white",
				"hideDetails": true
			}, null), !props.hideAlpha && createVNode(VSlider, {
				"class": "v-color-picker-preview__track v-color-picker-preview__alpha",
				"modelValue": props.color?.a ?? 1,
				"onUpdate:modelValue": (a) => emit("update:color", {
					...props.color ?? nullColor,
					a
				}),
				"step": 1 / 256,
				"min": 0,
				"max": 1,
				"disabled": props.disabled,
				"thumbSize": 14,
				"trackSize": 8,
				"trackFillColor": "white",
				"hideDetails": true
			}, null)])
		]));
		return {};
	}
});
var colors_default = {
	red: {
		base: "#f44336",
		lighten5: "#ffebee",
		lighten4: "#ffcdd2",
		lighten3: "#ef9a9a",
		lighten2: "#e57373",
		lighten1: "#ef5350",
		darken1: "#e53935",
		darken2: "#d32f2f",
		darken3: "#c62828",
		darken4: "#b71c1c",
		accent1: "#ff8a80",
		accent2: "#ff5252",
		accent3: "#ff1744",
		accent4: "#d50000"
	},
	pink: {
		base: "#e91e63",
		lighten5: "#fce4ec",
		lighten4: "#f8bbd0",
		lighten3: "#f48fb1",
		lighten2: "#f06292",
		lighten1: "#ec407a",
		darken1: "#d81b60",
		darken2: "#c2185b",
		darken3: "#ad1457",
		darken4: "#880e4f",
		accent1: "#ff80ab",
		accent2: "#ff4081",
		accent3: "#f50057",
		accent4: "#c51162"
	},
	purple: {
		base: "#9c27b0",
		lighten5: "#f3e5f5",
		lighten4: "#e1bee7",
		lighten3: "#ce93d8",
		lighten2: "#ba68c8",
		lighten1: "#ab47bc",
		darken1: "#8e24aa",
		darken2: "#7b1fa2",
		darken3: "#6a1b9a",
		darken4: "#4a148c",
		accent1: "#ea80fc",
		accent2: "#e040fb",
		accent3: "#d500f9",
		accent4: "#aa00ff"
	},
	deepPurple: {
		base: "#673ab7",
		lighten5: "#ede7f6",
		lighten4: "#d1c4e9",
		lighten3: "#b39ddb",
		lighten2: "#9575cd",
		lighten1: "#7e57c2",
		darken1: "#5e35b1",
		darken2: "#512da8",
		darken3: "#4527a0",
		darken4: "#311b92",
		accent1: "#b388ff",
		accent2: "#7c4dff",
		accent3: "#651fff",
		accent4: "#6200ea"
	},
	indigo: {
		base: "#3f51b5",
		lighten5: "#e8eaf6",
		lighten4: "#c5cae9",
		lighten3: "#9fa8da",
		lighten2: "#7986cb",
		lighten1: "#5c6bc0",
		darken1: "#3949ab",
		darken2: "#303f9f",
		darken3: "#283593",
		darken4: "#1a237e",
		accent1: "#8c9eff",
		accent2: "#536dfe",
		accent3: "#3d5afe",
		accent4: "#304ffe"
	},
	blue: {
		base: "#2196f3",
		lighten5: "#e3f2fd",
		lighten4: "#bbdefb",
		lighten3: "#90caf9",
		lighten2: "#64b5f6",
		lighten1: "#42a5f5",
		darken1: "#1e88e5",
		darken2: "#1976d2",
		darken3: "#1565c0",
		darken4: "#0d47a1",
		accent1: "#82b1ff",
		accent2: "#448aff",
		accent3: "#2979ff",
		accent4: "#2962ff"
	},
	lightBlue: {
		base: "#03a9f4",
		lighten5: "#e1f5fe",
		lighten4: "#b3e5fc",
		lighten3: "#81d4fa",
		lighten2: "#4fc3f7",
		lighten1: "#29b6f6",
		darken1: "#039be5",
		darken2: "#0288d1",
		darken3: "#0277bd",
		darken4: "#01579b",
		accent1: "#80d8ff",
		accent2: "#40c4ff",
		accent3: "#00b0ff",
		accent4: "#0091ea"
	},
	cyan: {
		base: "#00bcd4",
		lighten5: "#e0f7fa",
		lighten4: "#b2ebf2",
		lighten3: "#80deea",
		lighten2: "#4dd0e1",
		lighten1: "#26c6da",
		darken1: "#00acc1",
		darken2: "#0097a7",
		darken3: "#00838f",
		darken4: "#006064",
		accent1: "#84ffff",
		accent2: "#18ffff",
		accent3: "#00e5ff",
		accent4: "#00b8d4"
	},
	teal: {
		base: "#009688",
		lighten5: "#e0f2f1",
		lighten4: "#b2dfdb",
		lighten3: "#80cbc4",
		lighten2: "#4db6ac",
		lighten1: "#26a69a",
		darken1: "#00897b",
		darken2: "#00796b",
		darken3: "#00695c",
		darken4: "#004d40",
		accent1: "#a7ffeb",
		accent2: "#64ffda",
		accent3: "#1de9b6",
		accent4: "#00bfa5"
	},
	green: {
		base: "#4caf50",
		lighten5: "#e8f5e9",
		lighten4: "#c8e6c9",
		lighten3: "#a5d6a7",
		lighten2: "#81c784",
		lighten1: "#66bb6a",
		darken1: "#43a047",
		darken2: "#388e3c",
		darken3: "#2e7d32",
		darken4: "#1b5e20",
		accent1: "#b9f6ca",
		accent2: "#69f0ae",
		accent3: "#00e676",
		accent4: "#00c853"
	},
	lightGreen: {
		base: "#8bc34a",
		lighten5: "#f1f8e9",
		lighten4: "#dcedc8",
		lighten3: "#c5e1a5",
		lighten2: "#aed581",
		lighten1: "#9ccc65",
		darken1: "#7cb342",
		darken2: "#689f38",
		darken3: "#558b2f",
		darken4: "#33691e",
		accent1: "#ccff90",
		accent2: "#b2ff59",
		accent3: "#76ff03",
		accent4: "#64dd17"
	},
	lime: {
		base: "#cddc39",
		lighten5: "#f9fbe7",
		lighten4: "#f0f4c3",
		lighten3: "#e6ee9c",
		lighten2: "#dce775",
		lighten1: "#d4e157",
		darken1: "#c0ca33",
		darken2: "#afb42b",
		darken3: "#9e9d24",
		darken4: "#827717",
		accent1: "#f4ff81",
		accent2: "#eeff41",
		accent3: "#c6ff00",
		accent4: "#aeea00"
	},
	yellow: {
		base: "#ffeb3b",
		lighten5: "#fffde7",
		lighten4: "#fff9c4",
		lighten3: "#fff59d",
		lighten2: "#fff176",
		lighten1: "#ffee58",
		darken1: "#fdd835",
		darken2: "#fbc02d",
		darken3: "#f9a825",
		darken4: "#f57f17",
		accent1: "#ffff8d",
		accent2: "#ffff00",
		accent3: "#ffea00",
		accent4: "#ffd600"
	},
	amber: {
		base: "#ffc107",
		lighten5: "#fff8e1",
		lighten4: "#ffecb3",
		lighten3: "#ffe082",
		lighten2: "#ffd54f",
		lighten1: "#ffca28",
		darken1: "#ffb300",
		darken2: "#ffa000",
		darken3: "#ff8f00",
		darken4: "#ff6f00",
		accent1: "#ffe57f",
		accent2: "#ffd740",
		accent3: "#ffc400",
		accent4: "#ffab00"
	},
	orange: {
		base: "#ff9800",
		lighten5: "#fff3e0",
		lighten4: "#ffe0b2",
		lighten3: "#ffcc80",
		lighten2: "#ffb74d",
		lighten1: "#ffa726",
		darken1: "#fb8c00",
		darken2: "#f57c00",
		darken3: "#ef6c00",
		darken4: "#e65100",
		accent1: "#ffd180",
		accent2: "#ffab40",
		accent3: "#ff9100",
		accent4: "#ff6d00"
	},
	deepOrange: {
		base: "#ff5722",
		lighten5: "#fbe9e7",
		lighten4: "#ffccbc",
		lighten3: "#ffab91",
		lighten2: "#ff8a65",
		lighten1: "#ff7043",
		darken1: "#f4511e",
		darken2: "#e64a19",
		darken3: "#d84315",
		darken4: "#bf360c",
		accent1: "#ff9e80",
		accent2: "#ff6e40",
		accent3: "#ff3d00",
		accent4: "#dd2c00"
	},
	brown: {
		base: "#795548",
		lighten5: "#efebe9",
		lighten4: "#d7ccc8",
		lighten3: "#bcaaa4",
		lighten2: "#a1887f",
		lighten1: "#8d6e63",
		darken1: "#6d4c41",
		darken2: "#5d4037",
		darken3: "#4e342e",
		darken4: "#3e2723"
	},
	blueGrey: {
		base: "#607d8b",
		lighten5: "#eceff1",
		lighten4: "#cfd8dc",
		lighten3: "#b0bec5",
		lighten2: "#90a4ae",
		lighten1: "#78909c",
		darken1: "#546e7a",
		darken2: "#455a64",
		darken3: "#37474f",
		darken4: "#263238"
	},
	grey: {
		base: "#9e9e9e",
		lighten5: "#fafafa",
		lighten4: "#f5f5f5",
		lighten3: "#eeeeee",
		lighten2: "#e0e0e0",
		lighten1: "#bdbdbd",
		darken1: "#757575",
		darken2: "#616161",
		darken3: "#424242",
		darken4: "#212121"
	},
	shades: {
		black: "#000000",
		white: "#ffffff",
		transparent: "#ffffff00"
	}
};
//#endregion
//#region node_modules/vuetify/lib/components/VColorPicker/VColorPickerSwatches.js
var makeVColorPickerSwatchesProps = propsFactory({
	swatches: {
		type: Array,
		default: () => parseDefaultColors(colors_default)
	},
	disabled: Boolean,
	color: Object,
	maxHeight: [Number, String],
	...makeComponentProps()
}, "VColorPickerSwatches");
function parseDefaultColors(colors) {
	return Object.keys(colors).map((key) => {
		const color = colors[key];
		return color.base ? [
			color.base,
			color.darken4,
			color.darken3,
			color.darken2,
			color.darken1,
			color.lighten1,
			color.lighten2,
			color.lighten3,
			color.lighten4,
			color.lighten5
		] : [
			color.black,
			color.white,
			color.transparent
		];
	});
}
var VColorPickerSwatches = defineComponent$1({
	name: "VColorPickerSwatches",
	props: makeVColorPickerSwatchesProps(),
	emits: { "update:color": (color) => true },
	setup(props, _ref) {
		let { emit } = _ref;
		useRender(() => createVNode("div", {
			"class": ["v-color-picker-swatches", props.class],
			"style": [{ maxHeight: convertToUnit(props.maxHeight) }, props.style]
		}, [createVNode("div", null, [props.swatches.map((swatch) => createVNode("div", { "class": "v-color-picker-swatches__swatch" }, [swatch.map((color) => {
			const rgba = parseColor(color);
			const hsva = RGBtoHSV(rgba);
			return createVNode("div", {
				"class": "v-color-picker-swatches__color",
				"onClick": () => hsva && emit("update:color", hsva)
			}, [createVNode("div", { "style": { background: RGBtoCSS(rgba) } }, [props.color && deepEqual(props.color, hsva) ? createVNode(VIcon, {
				"size": "x-small",
				"icon": "$success",
				"color": getContrast(color, "#FFFFFF") > 2 ? "white" : "black"
			}, null) : void 0])]);
		})]))])]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/labs/VPicker/VPickerTitle.js
var VPickerTitle = createSimpleFunctional("v-picker-title");
//#endregion
//#region node_modules/vuetify/lib/components/VSheet/VSheet.js
var makeVSheetProps = propsFactory({
	color: String,
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeElevationProps(),
	...makeLocationProps(),
	...makePositionProps(),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VSheet");
var VSheet = genericComponent()({
	name: "VSheet",
	props: makeVSheetProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { borderClasses } = useBorder(props);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { locationStyles } = useLocation(props);
		const { positionClasses } = usePosition(props);
		const { roundedClasses } = useRounded(props);
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-sheet",
				themeClasses.value,
				backgroundColorClasses.value,
				borderClasses.value,
				elevationClasses.value,
				positionClasses.value,
				roundedClasses.value,
				props.class
			],
			"style": [
				backgroundColorStyles.value,
				dimensionStyles.value,
				locationStyles.value,
				props.style
			]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/labs/VPicker/VPicker.js
var makeVPickerProps = propsFactory({
	bgColor: String,
	divided: Boolean,
	landscape: Boolean,
	title: String,
	hideHeader: Boolean,
	...makeVSheetProps()
}, "VPicker");
var VPicker = genericComponent()({
	name: "VPicker",
	props: makeVPickerProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		useRender(() => {
			const sheetProps = VSheet.filterProps(props);
			const hasTitle = !!(props.title || slots.title);
			return createVNode(VSheet, mergeProps(sheetProps, {
				"color": props.bgColor,
				"class": [
					"v-picker",
					{
						"v-picker--divided": props.divided,
						"v-picker--landscape": props.landscape,
						"v-picker--with-actions": !!slots.actions
					},
					props.class
				],
				"style": props.style
			}), { default: () => [
				!props.hideHeader && createVNode("div", {
					"key": "header",
					"class": [backgroundColorClasses.value],
					"style": [backgroundColorStyles.value]
				}, [hasTitle && createVNode(VPickerTitle, { "key": "picker-title" }, { default: () => [slots.title?.() ?? props.title] }), slots.header && createVNode("div", { "class": "v-picker__header" }, [slots.header()])]),
				createVNode("div", { "class": "v-picker__body" }, [slots.default?.()]),
				slots.actions && createVNode(VDefaultsProvider, { "defaults": { VBtn: {
					slim: true,
					variant: "text"
				} } }, { default: () => [createVNode("div", { "class": "v-picker__actions" }, [slots.actions()])] })
			] });
		});
		return {};
	}
});
var VColorPicker = defineComponent$1({
	name: "VColorPicker",
	props: propsFactory({
		canvasHeight: {
			type: [String, Number],
			default: 150
		},
		disabled: Boolean,
		dotSize: {
			type: [Number, String],
			default: 10
		},
		hideCanvas: Boolean,
		hideSliders: Boolean,
		hideInputs: Boolean,
		mode: {
			type: String,
			default: "rgba",
			validator: (v) => Object.keys(modes).includes(v)
		},
		modes: {
			type: Array,
			default: () => Object.keys(modes),
			validator: (v) => Array.isArray(v) && v.every((m) => Object.keys(modes).includes(m))
		},
		showSwatches: Boolean,
		swatches: Array,
		swatchesMaxHeight: {
			type: [Number, String],
			default: 150
		},
		modelValue: { type: [Object, String] },
		...makeVPickerProps({ hideHeader: true })
	}, "VColorPicker")(),
	emits: {
		"update:modelValue": (color) => true,
		"update:mode": (mode) => true
	},
	setup(props, _ref) {
		let { slots } = _ref;
		const mode = useProxiedModel(props, "mode");
		const hue = ref(null);
		const model = useProxiedModel(props, "modelValue", void 0, (v) => {
			if (v == null || v === "") return null;
			let c;
			try {
				c = RGBtoHSV(parseColor(v));
			} catch (err) {
				consoleWarn(err);
				return null;
			}
			return c;
		}, (v) => {
			if (!v) return null;
			return extractColor(v, props.modelValue);
		});
		const currentColor = computed(() => {
			return model.value ? {
				...model.value,
				h: hue.value ?? model.value.h
			} : null;
		});
		const { rtlClasses } = useRtl();
		let externalChange = true;
		watch(model, (v) => {
			if (!externalChange) {
				externalChange = true;
				return;
			}
			if (!v) return;
			hue.value = v.h;
		}, { immediate: true });
		const updateColor = (hsva) => {
			externalChange = false;
			hue.value = hsva.h;
			model.value = hsva;
		};
		onBeforeMount(() => {
			if (!props.modes.includes(mode.value)) mode.value = props.modes[0];
		});
		provideDefaults({ VSlider: {
			color: void 0,
			trackColor: void 0,
			trackFillColor: void 0
		} });
		useRender(() => {
			return createVNode(VPicker, mergeProps(VPicker.filterProps(props), {
				"class": [
					"v-color-picker",
					rtlClasses.value,
					props.class
				],
				"style": [{ "--v-color-picker-color-hsv": HSVtoCSS({
					...currentColor.value ?? nullColor,
					a: 1
				}) }, props.style]
			}), {
				...slots,
				default: () => createVNode(Fragment, null, [
					!props.hideCanvas && createVNode(VColorPickerCanvas, {
						"key": "canvas",
						"color": currentColor.value,
						"onUpdate:color": updateColor,
						"disabled": props.disabled,
						"dotSize": props.dotSize,
						"width": props.width,
						"height": props.canvasHeight
					}, null),
					(!props.hideSliders || !props.hideInputs) && createVNode("div", {
						"key": "controls",
						"class": "v-color-picker__controls"
					}, [!props.hideSliders && createVNode(VColorPickerPreview, {
						"key": "preview",
						"color": currentColor.value,
						"onUpdate:color": updateColor,
						"hideAlpha": !mode.value.endsWith("a"),
						"disabled": props.disabled
					}, null), !props.hideInputs && createVNode(VColorPickerEdit, {
						"key": "edit",
						"modes": props.modes,
						"mode": mode.value,
						"onUpdate:mode": (m) => mode.value = m,
						"color": currentColor.value,
						"onUpdate:color": updateColor,
						"disabled": props.disabled
					}, null)]),
					props.showSwatches && createVNode(VColorPickerSwatches, {
						"key": "swatches",
						"color": currentColor.value,
						"onUpdate:color": updateColor,
						"maxHeight": props.swatchesMaxHeight,
						"swatches": props.swatches,
						"disabled": props.disabled
					}, null)
				])
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VCombobox/VCombobox.js
var makeVComboboxProps = propsFactory({
	autoSelectFirst: { type: [Boolean, String] },
	clearOnSelect: {
		type: Boolean,
		default: true
	},
	delimiters: Array,
	...makeFilterProps({ filterKeys: ["title"] }),
	...makeSelectProps({
		hideNoData: true,
		returnObject: true
	}),
	...omit(makeVTextFieldProps({
		modelValue: null,
		role: "combobox"
	}), [
		"validationValue",
		"dirty",
		"appendInnerIcon"
	]),
	...makeTransitionProps({ transition: false })
}, "VCombobox");
var VCombobox = genericComponent()({
	name: "VCombobox",
	props: makeVComboboxProps(),
	emits: {
		"update:focused": (focused) => true,
		"update:modelValue": (value) => true,
		"update:search": (value) => true,
		"update:menu": (value) => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const { t } = useLocale();
		const vTextFieldRef = ref();
		const isFocused = shallowRef(false);
		const isPristine = shallowRef(true);
		const listHasFocus = shallowRef(false);
		const vMenuRef = ref();
		const vVirtualScrollRef = ref();
		const selectionIndex = shallowRef(-1);
		let cleared = false;
		const { items, transformIn, transformOut } = useItems(props);
		const { textColorClasses, textColorStyles } = useTextColor(() => vTextFieldRef.value?.color);
		const model = useProxiedModel(props, "modelValue", [], (v) => transformIn(wrapInArray(v)), (v) => {
			const transformed = transformOut(v);
			return props.multiple ? transformed : transformed[0] ?? null;
		});
		const form = useForm$1(props);
		const hasChips = computed(() => !!(props.chips || slots.chip));
		const hasSelectionSlot = computed(() => hasChips.value || !!slots.selection);
		const _search = shallowRef(!props.multiple && !hasSelectionSlot.value ? model.value[0]?.title ?? "" : "");
		const search = computed({
			get: () => {
				return _search.value;
			},
			set: (val) => {
				_search.value = val ?? "";
				if (!props.multiple && !hasSelectionSlot.value) model.value = [transformItem$3(props, val)];
				if (val && props.multiple && props.delimiters?.length) {
					const values = val.split(new RegExp(`(?:${props.delimiters.join("|")})+`));
					if (values.length > 1) {
						values.forEach((v) => {
							v = v.trim();
							if (v) select(transformItem$3(props, v));
						});
						_search.value = "";
					}
				}
				if (!val) selectionIndex.value = -1;
				isPristine.value = !val;
			}
		});
		const counterValue = computed(() => {
			return typeof props.counterValue === "function" ? props.counterValue(model.value) : typeof props.counterValue === "number" ? props.counterValue : props.multiple ? model.value.length : search.value.length;
		});
		const { filteredItems, getMatches } = useFilter(props, items, () => isPristine.value ? "" : search.value);
		const displayItems = computed(() => {
			if (props.hideSelected) return filteredItems.value.filter((filteredItem) => !model.value.some((s) => s.value === filteredItem.value));
			return filteredItems.value;
		});
		const menuDisabled = computed(() => props.hideNoData && !displayItems.value.length || form.isReadonly.value || form.isDisabled.value);
		const _menu = useProxiedModel(props, "menu");
		const menu = computed({
			get: () => _menu.value,
			set: (v) => {
				if (_menu.value && !v && vMenuRef.value?.ΨopenChildren.size) return;
				if (v && menuDisabled.value) return;
				_menu.value = v;
			}
		});
		const label = toRef(() => menu.value ? props.closeText : props.openText);
		watch(_search, (value) => {
			if (cleared) nextTick(() => cleared = false);
			else if (isFocused.value && !menu.value) menu.value = true;
			emit("update:search", value);
		});
		watch(model, (value) => {
			if (!props.multiple && !hasSelectionSlot.value) _search.value = value[0]?.title ?? "";
		});
		const selectedValues = computed(() => model.value.map((selection) => selection.value));
		const highlightFirst = computed(() => {
			return (props.autoSelectFirst === true || props.autoSelectFirst === "exact" && search.value === displayItems.value[0]?.title) && displayItems.value.length > 0 && !isPristine.value && !listHasFocus.value;
		});
		const listRef = ref();
		const listEvents = useScrolling(listRef, vTextFieldRef);
		function onClear(e) {
			cleared = true;
			if (props.openOnClear) menu.value = true;
		}
		function onMousedownControl() {
			if (menuDisabled.value) return;
			menu.value = true;
		}
		function onMousedownMenuIcon(e) {
			if (menuDisabled.value) return;
			if (isFocused.value) {
				e.preventDefault();
				e.stopPropagation();
			}
			menu.value = !menu.value;
		}
		function onListKeydown(e) {
			if (e.key !== " " && checkPrintable(e)) vTextFieldRef.value?.focus();
		}
		function onKeydown(e) {
			if (isComposingIgnoreKey(e) || form.isReadonly.value) return;
			const selectionStart = vTextFieldRef.value?.selectionStart;
			const length = model.value.length;
			if ([
				"Enter",
				"ArrowDown",
				"ArrowUp"
			].includes(e.key)) e.preventDefault();
			if (["Enter", "ArrowDown"].includes(e.key)) menu.value = true;
			if (["Escape"].includes(e.key)) menu.value = false;
			if ([
				"Enter",
				"Escape",
				"Tab"
			].includes(e.key)) {
				if (highlightFirst.value && ["Enter", "Tab"].includes(e.key) && !model.value.some((_ref2) => {
					let { value } = _ref2;
					return value === displayItems.value[0].value;
				})) select(filteredItems.value[0]);
				isPristine.value = true;
			}
			if (e.key === "ArrowDown" && highlightFirst.value) listRef.value?.focus("next");
			if (e.key === "Enter" && search.value) {
				select(transformItem$3(props, search.value));
				if (hasSelectionSlot.value) _search.value = "";
			}
			if (["Backspace", "Delete"].includes(e.key)) {
				if (!props.multiple && hasSelectionSlot.value && model.value.length > 0 && !search.value) return select(model.value[0], false);
				if (~selectionIndex.value) {
					e.preventDefault();
					const originalSelectionIndex = selectionIndex.value;
					select(model.value[selectionIndex.value], false);
					selectionIndex.value = originalSelectionIndex >= length - 1 ? length - 2 : originalSelectionIndex;
				} else if (e.key === "Backspace" && !search.value) selectionIndex.value = length - 1;
				return;
			}
			if (!props.multiple) return;
			if (e.key === "ArrowLeft") {
				if (selectionIndex.value < 0 && selectionStart && selectionStart > 0) return;
				const prev = selectionIndex.value > -1 ? selectionIndex.value - 1 : length - 1;
				if (model.value[prev]) selectionIndex.value = prev;
				else {
					selectionIndex.value = -1;
					vTextFieldRef.value?.setSelectionRange(search.value.length, search.value.length);
				}
			} else if (e.key === "ArrowRight") {
				if (selectionIndex.value < 0) return;
				const next = selectionIndex.value + 1;
				if (model.value[next]) selectionIndex.value = next;
				else {
					selectionIndex.value = -1;
					vTextFieldRef.value?.setSelectionRange(0, 0);
				}
			} else if (~selectionIndex.value && checkPrintable(e)) selectionIndex.value = -1;
		}
		function onAfterEnter() {
			if (props.eager) vVirtualScrollRef.value?.calculateVisibleItems();
		}
		function onAfterLeave() {
			if (isFocused.value) {
				isPristine.value = true;
				vTextFieldRef.value?.focus();
			}
		}
		/** @param set - null means toggle */
		function select(item) {
			let set = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : true;
			if (!item || item.props.disabled) return;
			if (props.multiple) {
				const index = model.value.findIndex((selection) => (props.valueComparator || deepEqual)(selection.value, item.value));
				const add = set == null ? !~index : set;
				if (~index) {
					const value = add ? [...model.value, item] : [...model.value];
					value.splice(index, 1);
					model.value = value;
				} else if (add) model.value = [...model.value, item];
				if (props.clearOnSelect) search.value = "";
			} else {
				const add = set !== false;
				model.value = add ? [item] : [];
				_search.value = add && !hasSelectionSlot.value ? item.title : "";
				nextTick(() => {
					menu.value = false;
					isPristine.value = true;
				});
			}
		}
		function onFocusin(e) {
			isFocused.value = true;
			setTimeout(() => {
				listHasFocus.value = true;
			});
		}
		function onFocusout(e) {
			listHasFocus.value = false;
		}
		function onUpdateModelValue(v) {
			if (v == null || v === "" && !props.multiple && !hasSelectionSlot.value) model.value = [];
		}
		watch(isFocused, (val, oldVal) => {
			if (val || val === oldVal) return;
			selectionIndex.value = -1;
			menu.value = false;
			if (search.value) {
				if (props.multiple) {
					select(transformItem$3(props, search.value));
					return;
				}
				if (!hasSelectionSlot.value) return;
				if (model.value.some((_ref3) => {
					let { title } = _ref3;
					return title === search.value;
				})) _search.value = "";
				else select(transformItem$3(props, search.value));
			}
		});
		watch(menu, () => {
			if (!props.hideSelected && menu.value && model.value.length) {
				const index = displayItems.value.findIndex((item) => model.value.some((s) => (props.valueComparator || deepEqual)(s.value, item.value)));
				IN_BROWSER && window.requestAnimationFrame(() => {
					index >= 0 && vVirtualScrollRef.value?.scrollToIndex(index);
				});
			}
		});
		watch(() => props.items, (newVal, oldVal) => {
			if (menu.value) return;
			if (isFocused.value && !oldVal.length && newVal.length) menu.value = true;
		});
		useRender(() => {
			const hasList = !!(!props.hideNoData || displayItems.value.length || slots["prepend-item"] || slots["append-item"] || slots["no-data"]);
			const isDirty = model.value.length > 0;
			const textFieldProps = VTextField.filterProps(props);
			return createVNode(VTextField, mergeProps({ "ref": vTextFieldRef }, textFieldProps, {
				"modelValue": search.value,
				"onUpdate:modelValue": [($event) => search.value = $event, onUpdateModelValue],
				"focused": isFocused.value,
				"onUpdate:focused": ($event) => isFocused.value = $event,
				"validationValue": model.externalValue,
				"counterValue": counterValue.value,
				"dirty": isDirty,
				"class": [
					"v-combobox",
					{
						"v-combobox--active-menu": menu.value,
						"v-combobox--chips": !!props.chips,
						"v-combobox--selection-slot": !!hasSelectionSlot.value,
						"v-combobox--selecting-index": selectionIndex.value > -1,
						[`v-combobox--${props.multiple ? "multiple" : "single"}`]: true
					},
					props.class
				],
				"style": props.style,
				"readonly": form.isReadonly.value,
				"placeholder": isDirty ? void 0 : props.placeholder,
				"onClick:clear": onClear,
				"onMousedown:control": onMousedownControl,
				"onKeydown": onKeydown
			}), {
				...slots,
				default: () => createVNode(Fragment, null, [createVNode(VMenu, mergeProps({
					"ref": vMenuRef,
					"modelValue": menu.value,
					"onUpdate:modelValue": ($event) => menu.value = $event,
					"activator": "parent",
					"contentClass": "v-combobox__content",
					"disabled": menuDisabled.value,
					"eager": props.eager,
					"maxHeight": 310,
					"openOnClick": false,
					"closeOnContentClick": false,
					"transition": props.transition,
					"onAfterEnter": onAfterEnter,
					"onAfterLeave": onAfterLeave
				}, props.menuProps), { default: () => [hasList && createVNode(VList, mergeProps({
					"ref": listRef,
					"selected": selectedValues.value,
					"selectStrategy": props.multiple ? "independent" : "single-independent",
					"onMousedown": (e) => e.preventDefault(),
					"onKeydown": onListKeydown,
					"onFocusin": onFocusin,
					"onFocusout": onFocusout,
					"tabindex": "-1",
					"aria-live": "polite",
					"color": props.itemColor ?? props.color
				}, listEvents, props.listProps), { default: () => [
					slots["prepend-item"]?.(),
					!displayItems.value.length && !props.hideNoData && (slots["no-data"]?.() ?? createVNode(VListItem, {
						"key": "no-data",
						"title": t(props.noDataText)
					}, null)),
					createVNode(VVirtualScroll, {
						"ref": vVirtualScrollRef,
						"renderless": true,
						"items": displayItems.value,
						"itemKey": "value"
					}, { default: (_ref4) => {
						let { item, index, itemRef } = _ref4;
						const itemProps = mergeProps(item.props, {
							ref: itemRef,
							key: item.value,
							active: highlightFirst.value && index === 0 ? true : void 0,
							onClick: () => select(item, null)
						});
						return slots.item?.({
							item,
							index,
							props: itemProps
						}) ?? createVNode(VListItem, mergeProps(itemProps, { "role": "option" }), {
							prepend: (_ref5) => {
								let { isSelected } = _ref5;
								return createVNode(Fragment, null, [
									props.multiple && !props.hideSelected ? createVNode(VCheckboxBtn, {
										"key": item.value,
										"modelValue": isSelected,
										"ripple": false,
										"tabindex": "-1"
									}, null) : void 0,
									item.props.prependAvatar && createVNode(VAvatar, { "image": item.props.prependAvatar }, null),
									item.props.prependIcon && createVNode(VIcon, { "icon": item.props.prependIcon }, null)
								]);
							},
							title: () => {
								return isPristine.value ? item.title : highlightResult("v-combobox", item.title, getMatches(item)?.title);
							}
						});
					} }),
					slots["append-item"]?.()
				] })] }), model.value.map((item, index) => {
					function onChipClose(e) {
						e.stopPropagation();
						e.preventDefault();
						select(item, false);
					}
					const slotProps = {
						"onClick:close": onChipClose,
						onKeydown(e) {
							if (e.key !== "Enter" && e.key !== " ") return;
							e.preventDefault();
							e.stopPropagation();
							onChipClose(e);
						},
						onMousedown(e) {
							e.preventDefault();
							e.stopPropagation();
						},
						modelValue: true,
						"onUpdate:modelValue": void 0
					};
					const hasSlot = hasChips.value ? !!slots.chip : !!slots.selection;
					const slotContent = hasSlot ? ensureValidVNode(hasChips.value ? slots.chip({
						item,
						index,
						props: slotProps
					}) : slots.selection({
						item,
						index
					})) : void 0;
					if (hasSlot && !slotContent) return void 0;
					return createVNode("div", {
						"key": item.value,
						"class": ["v-combobox__selection", index === selectionIndex.value && ["v-combobox__selection--selected", textColorClasses.value]],
						"style": index === selectionIndex.value ? textColorStyles.value : {}
					}, [hasChips.value ? !slots.chip ? createVNode(VChip, mergeProps({
						"key": "chip",
						"closable": props.closableChips,
						"size": "small",
						"text": item.title,
						"disabled": item.props.disabled
					}, slotProps), null) : createVNode(VDefaultsProvider, {
						"key": "chip-defaults",
						"defaults": { VChip: {
							closable: props.closableChips,
							size: "small",
							text: item.title
						} }
					}, { default: () => [slotContent] }) : slotContent ?? createVNode("span", { "class": "v-combobox__selection-text" }, [item.title, props.multiple && index < model.value.length - 1 && createVNode("span", { "class": "v-combobox__selection-comma" }, [createTextVNode(",")])])]);
				})]),
				"append-inner": function() {
					for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
					return createVNode(Fragment, null, [slots["append-inner"]?.(...args), (!props.hideNoData || props.items.length) && props.menuIcon ? createVNode(VIcon, {
						"class": "v-combobox__menu-icon",
						"color": vTextFieldRef.value?.fieldIconColor,
						"icon": props.menuIcon,
						"onMousedown": onMousedownMenuIcon,
						"onClick": noop,
						"aria-label": t(label.value),
						"title": t(label.value),
						"tabindex": "-1"
					}, null) : void 0]);
				}
			});
		});
		return forwardRefs({
			isFocused,
			isPristine,
			menu,
			search,
			selectionIndex,
			filteredItems,
			select
		}, vTextFieldRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VConfirmEdit/VConfirmEdit.js
var makeVConfirmEditProps = propsFactory({
	modelValue: null,
	color: String,
	cancelText: {
		type: String,
		default: "$vuetify.confirmEdit.cancel"
	},
	okText: {
		type: String,
		default: "$vuetify.confirmEdit.ok"
	},
	disabled: {
		type: [Boolean, Array],
		default: void 0
	},
	hideActions: Boolean
}, "VConfirmEdit");
var VConfirmEdit = genericComponent()({
	name: "VConfirmEdit",
	props: makeVConfirmEditProps(),
	emits: {
		cancel: () => true,
		save: (value) => true,
		"update:modelValue": (value) => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const internalModel = ref();
		watchEffect(() => {
			internalModel.value = structuredClone(toRaw(model.value));
		});
		const { t } = useLocale();
		const isPristine = computed(() => {
			return deepEqual(model.value, internalModel.value);
		});
		function isActionDisabled(action) {
			if (typeof props.disabled === "boolean") return props.disabled;
			if (Array.isArray(props.disabled)) return props.disabled.includes(action);
			return isPristine.value;
		}
		const isSaveDisabled = computed(() => isActionDisabled("save"));
		const isCancelDisabled = computed(() => isActionDisabled("cancel"));
		function save() {
			model.value = internalModel.value;
			emit("save", internalModel.value);
		}
		function cancel() {
			internalModel.value = structuredClone(toRaw(model.value));
			emit("cancel");
		}
		function actions(actionsProps) {
			return createVNode(Fragment, null, [createVNode(VBtn, mergeProps({
				"disabled": isCancelDisabled.value,
				"variant": "text",
				"color": props.color,
				"onClick": cancel,
				"text": t(props.cancelText)
			}, actionsProps), null), createVNode(VBtn, mergeProps({
				"disabled": isSaveDisabled.value,
				"variant": "text",
				"color": props.color,
				"onClick": save,
				"text": t(props.okText)
			}, actionsProps), null)]);
		}
		let actionsUsed = false;
		useRender(() => {
			return createVNode(Fragment, null, [slots.default?.({
				model: internalModel,
				save,
				cancel,
				isPristine: isPristine.value,
				get actions() {
					actionsUsed = true;
					return actions;
				}
			}), !props.hideActions && !actionsUsed && actions()]);
		});
		return {
			save,
			cancel,
			isPristine
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/expand.js
var makeDataTableExpandProps = propsFactory({
	expandOnClick: Boolean,
	showExpand: Boolean,
	expanded: {
		type: Array,
		default: () => []
	}
}, "DataTable-expand");
var VDataTableExpandedKey = Symbol.for("vuetify:datatable:expanded");
function provideExpanded(props) {
	const expandOnClick = toRef(() => props.expandOnClick);
	const expanded = useProxiedModel(props, "expanded", props.expanded, (v) => {
		return new Set(v);
	}, (v) => {
		return [...v.values()];
	});
	function expand(item, value) {
		const newExpanded = new Set(expanded.value);
		if (!value) newExpanded.delete(item.value);
		else newExpanded.add(item.value);
		expanded.value = newExpanded;
	}
	function isExpanded(item) {
		return expanded.value.has(item.value);
	}
	function toggleExpand(item) {
		expand(item, !isExpanded(item));
	}
	const data = {
		expand,
		expanded,
		expandOnClick,
		isExpanded,
		toggleExpand
	};
	provide(VDataTableExpandedKey, data);
	return data;
}
function useExpanded() {
	const data = inject(VDataTableExpandedKey);
	if (!data) throw new Error("foo");
	return data;
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/group.js
var makeDataTableGroupProps = propsFactory({ groupBy: {
	type: Array,
	default: () => []
} }, "DataTable-group");
var VDataTableGroupSymbol = Symbol.for("vuetify:data-table-group");
function createGroupBy(props) {
	return { groupBy: useProxiedModel(props, "groupBy") };
}
function provideGroupBy(options) {
	const { disableSort, groupBy, sortBy } = options;
	const opened = ref(/* @__PURE__ */ new Set());
	const sortByWithGroups = computed(() => {
		return groupBy.value.map((val) => ({
			...val,
			order: val.order ?? false
		})).concat(disableSort?.value ? [] : sortBy.value);
	});
	function isGroupOpen(group) {
		return opened.value.has(group.id);
	}
	function toggleGroup(group) {
		const newOpened = new Set(opened.value);
		if (!isGroupOpen(group)) newOpened.add(group.id);
		else newOpened.delete(group.id);
		opened.value = newOpened;
	}
	function extractRows(items) {
		function dive(group) {
			const arr = [];
			for (const item of group.items) if ("type" in item && item.type === "group") arr.push(...dive(item));
			else arr.push(item);
			return [...new Set(arr)];
		}
		return dive({
			type: "group",
			items,
			id: "dummy",
			key: "dummy",
			value: "dummy",
			depth: 0
		});
	}
	const data = {
		sortByWithGroups,
		toggleGroup,
		opened,
		groupBy,
		extractRows,
		isGroupOpen
	};
	provide(VDataTableGroupSymbol, data);
	return data;
}
function useGroupBy() {
	const data = inject(VDataTableGroupSymbol);
	if (!data) throw new Error("Missing group!");
	return data;
}
function groupItemsByProperty(items, groupBy) {
	if (!items.length) return [];
	const groups = /* @__PURE__ */ new Map();
	for (const item of items) {
		const value = getObjectValueByPath(item.raw, groupBy);
		if (!groups.has(value)) groups.set(value, []);
		groups.get(value).push(item);
	}
	return groups;
}
function groupItems(items, groupBy) {
	let depth = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : 0;
	let prefix = arguments.length > 3 && arguments[3] !== void 0 ? arguments[3] : "root";
	if (!groupBy.length) return [];
	const groupedItems = groupItemsByProperty(items, groupBy[0]);
	const groups = [];
	const rest = groupBy.slice(1);
	groupedItems.forEach((items, value) => {
		const key = groupBy[0];
		const id = `${prefix}_${key}_${value}`;
		groups.push({
			depth,
			id,
			key,
			value,
			items: rest.length ? groupItems(items, rest, depth + 1, id) : items,
			type: "group"
		});
	});
	return groups;
}
function flattenItems(items, opened) {
	const flatItems = [];
	for (const item of items) if ("type" in item && item.type === "group") {
		if (item.value != null) flatItems.push(item);
		if (opened.has(item.id) || item.value == null) flatItems.push(...flattenItems(item.items, opened));
	} else flatItems.push(item);
	return flatItems;
}
function useGroupedItems(items, groupBy, opened) {
	return { flatItems: computed(() => {
		if (!groupBy.value.length) return items.value;
		return flattenItems(groupItems(items.value, groupBy.value.map((item) => item.key)), opened.value);
	}) };
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/options.js
function useOptions(_ref) {
	let { page, itemsPerPage, sortBy, groupBy, search } = _ref;
	const vm = getCurrentInstance$1("VDataTable");
	const options = () => ({
		page: page.value,
		itemsPerPage: itemsPerPage.value,
		sortBy: sortBy.value,
		groupBy: groupBy.value,
		search: search.value
	});
	let oldOptions = null;
	watch(options, (value) => {
		if (deepEqual(oldOptions, value)) return;
		if (oldOptions && oldOptions.search !== value.search) page.value = 1;
		vm.emit("update:options", value);
		oldOptions = value;
	}, {
		deep: true,
		immediate: true
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/paginate.js
var makeDataTablePaginateProps = propsFactory({
	page: {
		type: [Number, String],
		default: 1
	},
	itemsPerPage: {
		type: [Number, String],
		default: 10
	}
}, "DataTable-paginate");
var VDataTablePaginationSymbol = Symbol.for("vuetify:data-table-pagination");
function createPagination(props) {
	return {
		page: useProxiedModel(props, "page", void 0, (value) => Number(value ?? 1)),
		itemsPerPage: useProxiedModel(props, "itemsPerPage", void 0, (value) => Number(value ?? 10))
	};
}
function providePagination(options) {
	const { page, itemsPerPage, itemsLength } = options;
	const startIndex = computed(() => {
		if (itemsPerPage.value === -1) return 0;
		return itemsPerPage.value * (page.value - 1);
	});
	const stopIndex = computed(() => {
		if (itemsPerPage.value === -1) return itemsLength.value;
		return Math.min(itemsLength.value, startIndex.value + itemsPerPage.value);
	});
	const pageCount = computed(() => {
		if (itemsPerPage.value === -1 || itemsLength.value === 0) return 1;
		return Math.ceil(itemsLength.value / itemsPerPage.value);
	});
	watch([page, pageCount], () => {
		if (page.value > pageCount.value) page.value = pageCount.value;
	});
	function setItemsPerPage(value) {
		itemsPerPage.value = value;
		page.value = 1;
	}
	function nextPage() {
		page.value = clamp(page.value + 1, 1, pageCount.value);
	}
	function prevPage() {
		page.value = clamp(page.value - 1, 1, pageCount.value);
	}
	function setPage(value) {
		page.value = clamp(value, 1, pageCount.value);
	}
	const data = {
		page,
		itemsPerPage,
		startIndex,
		stopIndex,
		pageCount,
		itemsLength,
		nextPage,
		prevPage,
		setPage,
		setItemsPerPage
	};
	provide(VDataTablePaginationSymbol, data);
	return data;
}
function usePagination() {
	const data = inject(VDataTablePaginationSymbol);
	if (!data) throw new Error("Missing pagination!");
	return data;
}
function usePaginatedItems(options) {
	const vm = getCurrentInstance$1("usePaginatedItems");
	const { items, startIndex, stopIndex, itemsPerPage } = options;
	const paginatedItems = computed(() => {
		if (itemsPerPage.value <= 0) return items.value;
		return items.value.slice(startIndex.value, stopIndex.value);
	});
	watch(paginatedItems, (val) => {
		vm.emit("update:currentItems", val);
	}, { immediate: true });
	return { paginatedItems };
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/select.js
var singleSelectStrategy = {
	showSelectAll: false,
	allSelected: () => [],
	select: (_ref) => {
		let { items, value } = _ref;
		return new Set(value ? [items[0]?.value] : []);
	},
	selectAll: (_ref2) => {
		let { selected } = _ref2;
		return selected;
	}
};
var pageSelectStrategy = {
	showSelectAll: true,
	allSelected: (_ref3) => {
		let { currentPage } = _ref3;
		return currentPage;
	},
	select: (_ref4) => {
		let { items, value, selected } = _ref4;
		for (const item of items) if (value) selected.add(item.value);
		else selected.delete(item.value);
		return selected;
	},
	selectAll: (_ref5) => {
		let { value, currentPage, selected } = _ref5;
		return pageSelectStrategy.select({
			items: currentPage,
			value,
			selected
		});
	}
};
var allSelectStrategy = {
	showSelectAll: true,
	allSelected: (_ref6) => {
		let { allItems } = _ref6;
		return allItems;
	},
	select: (_ref7) => {
		let { items, value, selected } = _ref7;
		for (const item of items) if (value) selected.add(item.value);
		else selected.delete(item.value);
		return selected;
	},
	selectAll: (_ref8) => {
		let { value, allItems, selected } = _ref8;
		return allSelectStrategy.select({
			items: allItems,
			value,
			selected
		});
	}
};
var makeDataTableSelectProps = propsFactory({
	showSelect: Boolean,
	selectStrategy: {
		type: [String, Object],
		default: "page"
	},
	modelValue: {
		type: Array,
		default: () => []
	},
	valueComparator: {
		type: Function,
		default: deepEqual
	}
}, "DataTable-select");
var VDataTableSelectionSymbol = Symbol.for("vuetify:data-table-selection");
function provideSelection(props, _ref9) {
	let { allItems, currentPage } = _ref9;
	const selected = useProxiedModel(props, "modelValue", props.modelValue, (v) => {
		return new Set(wrapInArray(v).map((v) => {
			return allItems.value.find((item) => props.valueComparator(v, item.value))?.value ?? v;
		}));
	}, (v) => {
		return [...v.values()];
	});
	const allSelectable = computed(() => allItems.value.filter((item) => item.selectable));
	const currentPageSelectable = computed(() => currentPage.value.filter((item) => item.selectable));
	const selectStrategy = computed(() => {
		if (typeof props.selectStrategy === "object") return props.selectStrategy;
		switch (props.selectStrategy) {
			case "single": return singleSelectStrategy;
			case "all": return allSelectStrategy;
			default: return pageSelectStrategy;
		}
	});
	const lastSelectedIndex = shallowRef(null);
	function isSelected(items) {
		return wrapInArray(items).every((item) => selected.value.has(item.value));
	}
	function isSomeSelected(items) {
		return wrapInArray(items).some((item) => selected.value.has(item.value));
	}
	function select(items, value) {
		selected.value = selectStrategy.value.select({
			items,
			value,
			selected: new Set(selected.value)
		});
	}
	function toggleSelect(item, index, event) {
		const items = [];
		index = index ?? currentPage.value.findIndex((i) => i.value === item.value);
		if (props.selectStrategy !== "single" && event?.shiftKey && lastSelectedIndex.value !== null) {
			const [start, end] = [lastSelectedIndex.value, index].sort((a, b) => a - b);
			items.push(...currentPage.value.slice(start, end + 1).filter((item) => item.selectable));
		} else {
			items.push(item);
			lastSelectedIndex.value = index;
		}
		select(items, !isSelected([item]));
	}
	function selectAll(value) {
		selected.value = selectStrategy.value.selectAll({
			value,
			allItems: allSelectable.value,
			currentPage: currentPageSelectable.value,
			selected: new Set(selected.value)
		});
	}
	const data = {
		toggleSelect,
		select,
		selectAll,
		isSelected,
		isSomeSelected,
		someSelected: computed(() => selected.value.size > 0),
		allSelected: computed(() => {
			const items = selectStrategy.value.allSelected({
				allItems: allSelectable.value,
				currentPage: currentPageSelectable.value
			});
			return !!items.length && isSelected(items);
		}),
		showSelectAll: toRef(() => selectStrategy.value.showSelectAll),
		lastSelectedIndex,
		selectStrategy
	};
	provide(VDataTableSelectionSymbol, data);
	return data;
}
function useSelection() {
	const data = inject(VDataTableSelectionSymbol);
	if (!data) throw new Error("Missing selection!");
	return data;
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/sort.js
var makeDataTableSortProps = propsFactory({
	sortBy: {
		type: Array,
		default: () => []
	},
	customKeySort: Object,
	multiSort: Boolean,
	mustSort: Boolean
}, "DataTable-sort");
var VDataTableSortSymbol = Symbol.for("vuetify:data-table-sort");
function createSort(props) {
	return {
		sortBy: useProxiedModel(props, "sortBy"),
		mustSort: toRef(() => props.mustSort),
		multiSort: toRef(() => props.multiSort)
	};
}
function provideSort(options) {
	const { sortBy, mustSort, multiSort, page } = options;
	const toggleSort = (column) => {
		if (column.key == null) return;
		let newSortBy = sortBy.value.map((x) => ({ ...x })) ?? [];
		const item = newSortBy.find((x) => x.key === column.key);
		if (!item) if (multiSort.value) newSortBy.push({
			key: column.key,
			order: "asc"
		});
		else newSortBy = [{
			key: column.key,
			order: "asc"
		}];
		else if (item.order === "desc") if (mustSort.value && newSortBy.length === 1) item.order = "asc";
		else newSortBy = newSortBy.filter((x) => x.key !== column.key);
		else item.order = "desc";
		sortBy.value = newSortBy;
		if (page) page.value = 1;
	};
	function isSorted(column) {
		return !!sortBy.value.find((item) => item.key === column.key);
	}
	const data = {
		sortBy,
		toggleSort,
		isSorted
	};
	provide(VDataTableSortSymbol, data);
	return data;
}
function useSort() {
	const data = inject(VDataTableSortSymbol);
	if (!data) throw new Error("Missing sort!");
	return data;
}
function useSortedItems(props, items, sortBy, options) {
	const locale = useLocale();
	return { sortedItems: computed(() => {
		if (!sortBy.value.length) return items.value;
		return sortItems(items.value, sortBy.value, locale.current.value, {
			transform: options?.transform,
			sortFunctions: {
				...props.customKeySort,
				...options?.sortFunctions?.value
			},
			sortRawFunctions: options?.sortRawFunctions?.value
		});
	}) };
}
function sortItems(items, sortByItems, locale, options) {
	const stringCollator = new Intl.Collator(locale, {
		sensitivity: "accent",
		usage: "sort"
	});
	return items.map((item) => [item, options?.transform ? options.transform(item) : item]).sort((a, b) => {
		for (let i = 0; i < sortByItems.length; i++) {
			let hasCustomResult = false;
			const sortKey = sortByItems[i].key;
			const sortOrder = sortByItems[i].order ?? "asc";
			if (sortOrder === false) continue;
			let sortA = getObjectValueByPath(a[1], sortKey);
			let sortB = getObjectValueByPath(b[1], sortKey);
			let sortARaw = a[0].raw;
			let sortBRaw = b[0].raw;
			if (sortOrder === "desc") {
				[sortA, sortB] = [sortB, sortA];
				[sortARaw, sortBRaw] = [sortBRaw, sortARaw];
			}
			if (options?.sortRawFunctions?.[sortKey]) {
				const customResult = options.sortRawFunctions[sortKey](sortARaw, sortBRaw);
				if (customResult == null) continue;
				hasCustomResult = true;
				if (customResult) return customResult;
			}
			if (options?.sortFunctions?.[sortKey]) {
				const customResult = options.sortFunctions[sortKey](sortA, sortB);
				if (customResult == null) continue;
				hasCustomResult = true;
				if (customResult) return customResult;
			}
			if (hasCustomResult) continue;
			if (sortA instanceof Date && sortB instanceof Date) return sortA.getTime() - sortB.getTime();
			[sortA, sortB] = [sortA, sortB].map((s) => s != null ? s.toString().toLocaleLowerCase() : s);
			if (sortA !== sortB) {
				if (isEmpty(sortA) && isEmpty(sortB)) return 0;
				if (isEmpty(sortA)) return -1;
				if (isEmpty(sortB)) return 1;
				if (!isNaN(sortA) && !isNaN(sortB)) return Number(sortA) - Number(sortB);
				return stringCollator.compare(sortA, sortB);
			}
		}
		return 0;
	}).map((_ref) => {
		let [item] = _ref;
		return item;
	});
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataIterator/composables/items.js
var makeDataIteratorItemsProps = propsFactory({
	items: {
		type: Array,
		default: () => []
	},
	itemValue: {
		type: [
			String,
			Array,
			Function
		],
		default: "id"
	},
	itemSelectable: {
		type: [
			String,
			Array,
			Function
		],
		default: null
	},
	returnObject: Boolean
}, "DataIterator-items");
function transformItem$1(props, item) {
	return {
		type: "item",
		value: props.returnObject ? item : getPropertyFromItem(item, props.itemValue),
		selectable: getPropertyFromItem(item, props.itemSelectable, true),
		raw: item
	};
}
function transformItems$1(props, items) {
	const array = [];
	for (const item of items) array.push(transformItem$1(props, item));
	return array;
}
function useDataIteratorItems(props) {
	return { items: computed(() => transformItems$1(props, props.items)) };
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataIterator/VDataIterator.js
var makeVDataIteratorProps = propsFactory({
	search: String,
	loading: Boolean,
	...makeComponentProps(),
	...makeDataIteratorItemsProps(),
	...makeDataTableSelectProps(),
	...makeDataTableSortProps(),
	...makeDataTablePaginateProps({ itemsPerPage: 5 }),
	...makeDataTableExpandProps(),
	...makeDataTableGroupProps(),
	...makeFilterProps(),
	...makeTagProps(),
	...makeTransitionProps({ transition: {
		component: VFadeTransition,
		hideOnLeave: true
	} })
}, "VDataIterator");
var VDataIterator = genericComponent()({
	name: "VDataIterator",
	props: makeVDataIteratorProps(),
	emits: {
		"update:modelValue": (value) => true,
		"update:groupBy": (value) => true,
		"update:page": (value) => true,
		"update:itemsPerPage": (value) => true,
		"update:sortBy": (value) => true,
		"update:options": (value) => true,
		"update:expanded": (value) => true,
		"update:currentItems": (value) => true
	},
	setup(props, _ref) {
		let { slots } = _ref;
		const groupBy = useProxiedModel(props, "groupBy");
		const search = toRef(() => props.search);
		const { items } = useDataIteratorItems(props);
		const { filteredItems } = useFilter(props, items, search, { transform: (item) => item.raw });
		const { sortBy, multiSort, mustSort } = createSort(props);
		const { page, itemsPerPage } = createPagination(props);
		const { toggleSort } = provideSort({
			sortBy,
			multiSort,
			mustSort,
			page
		});
		const { sortByWithGroups, opened, extractRows, isGroupOpen, toggleGroup } = provideGroupBy({
			groupBy,
			sortBy
		});
		const { sortedItems } = useSortedItems(props, filteredItems, sortByWithGroups, { transform: (item) => item.raw });
		const { flatItems } = useGroupedItems(sortedItems, groupBy, opened);
		const { startIndex, stopIndex, pageCount, prevPage, nextPage, setItemsPerPage, setPage } = providePagination({
			page,
			itemsPerPage,
			itemsLength: toRef(() => flatItems.value.length)
		});
		const { paginatedItems } = usePaginatedItems({
			items: flatItems,
			startIndex,
			stopIndex,
			itemsPerPage
		});
		const paginatedItemsWithoutGroups = computed(() => extractRows(paginatedItems.value));
		const { isSelected, select, selectAll, toggleSelect } = provideSelection(props, {
			allItems: items,
			currentPage: paginatedItemsWithoutGroups
		});
		const { isExpanded, toggleExpand } = provideExpanded(props);
		useOptions({
			page,
			itemsPerPage,
			sortBy,
			groupBy,
			search
		});
		const slotProps = computed(() => ({
			page: page.value,
			itemsPerPage: itemsPerPage.value,
			sortBy: sortBy.value,
			pageCount: pageCount.value,
			toggleSort,
			prevPage,
			nextPage,
			setPage,
			setItemsPerPage,
			isSelected,
			select,
			selectAll,
			toggleSelect,
			isExpanded,
			toggleExpand,
			isGroupOpen,
			toggleGroup,
			items: paginatedItemsWithoutGroups.value,
			groupedItems: paginatedItems.value
		}));
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-data-iterator",
				{ "v-data-iterator--loading": props.loading },
				props.class
			],
			"style": props.style
		}, { default: () => [
			slots.header?.(slotProps.value),
			createVNode(MaybeTransition, { "transition": props.transition }, { default: () => [props.loading ? createVNode(LoaderSlot, {
				"key": "loader",
				"name": "v-data-iterator",
				"active": true
			}, { default: (slotProps) => slots.loader?.(slotProps) }) : createVNode("div", { "key": "items" }, [!paginatedItems.value.length ? slots["no-data"]?.() : slots.default?.(slotProps.value)])] }),
			slots.footer?.(slotProps.value)
		] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/refs.js
function useRefs() {
	const refs = ref([]);
	onBeforeUpdate(() => refs.value = []);
	function updateRef(e, i) {
		refs.value[i] = e;
	}
	return {
		refs,
		updateRef
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VPagination/VPagination.js
var makeVPaginationProps = propsFactory({
	activeColor: String,
	start: {
		type: [Number, String],
		default: 1
	},
	modelValue: {
		type: Number,
		default: (props) => props.start
	},
	disabled: Boolean,
	length: {
		type: [Number, String],
		default: 1,
		validator: (val) => val % 1 === 0
	},
	totalVisible: [Number, String],
	firstIcon: {
		type: IconValue,
		default: "$first"
	},
	prevIcon: {
		type: IconValue,
		default: "$prev"
	},
	nextIcon: {
		type: IconValue,
		default: "$next"
	},
	lastIcon: {
		type: IconValue,
		default: "$last"
	},
	ariaLabel: {
		type: String,
		default: "$vuetify.pagination.ariaLabel.root"
	},
	pageAriaLabel: {
		type: String,
		default: "$vuetify.pagination.ariaLabel.page"
	},
	currentPageAriaLabel: {
		type: String,
		default: "$vuetify.pagination.ariaLabel.currentPage"
	},
	firstAriaLabel: {
		type: String,
		default: "$vuetify.pagination.ariaLabel.first"
	},
	previousAriaLabel: {
		type: String,
		default: "$vuetify.pagination.ariaLabel.previous"
	},
	nextAriaLabel: {
		type: String,
		default: "$vuetify.pagination.ariaLabel.next"
	},
	lastAriaLabel: {
		type: String,
		default: "$vuetify.pagination.ariaLabel.last"
	},
	ellipsis: {
		type: String,
		default: "..."
	},
	showFirstLastPage: Boolean,
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeElevationProps(),
	...makeRoundedProps(),
	...makeSizeProps(),
	...makeTagProps({ tag: "nav" }),
	...makeThemeProps(),
	...makeVariantProps({ variant: "text" })
}, "VPagination");
var VPagination = genericComponent()({
	name: "VPagination",
	props: makeVPaginationProps(),
	emits: {
		"update:modelValue": (value) => true,
		first: (value) => true,
		prev: (value) => true,
		next: (value) => true,
		last: (value) => true
	},
	setup(props, _ref) {
		let { slots, emit } = _ref;
		const page = useProxiedModel(props, "modelValue");
		const { t, n } = useLocale();
		const { isRtl } = useRtl();
		const { themeClasses } = provideTheme(props);
		const { width } = useDisplay();
		const maxButtons = shallowRef(-1);
		provideDefaults(void 0, { scoped: true });
		const { resizeRef } = useResizeObserver((entries) => {
			if (!entries.length) return;
			const { target, contentRect } = entries[0];
			const firstItem = target.querySelector(".v-pagination__list > *");
			if (!firstItem) return;
			const totalWidth = contentRect.width;
			maxButtons.value = getMax(totalWidth, firstItem.offsetWidth + parseFloat(getComputedStyle(firstItem).marginRight) * 2);
		});
		const length = computed(() => parseInt(props.length, 10));
		const start = computed(() => parseInt(props.start, 10));
		const totalVisible = computed(() => {
			if (props.totalVisible != null) return parseInt(props.totalVisible, 10);
			else if (maxButtons.value >= 0) return maxButtons.value;
			return getMax(width.value, 58);
		});
		function getMax(totalWidth, itemWidth) {
			const minButtons = props.showFirstLastPage ? 5 : 3;
			return Math.max(0, Math.floor(Number(((totalWidth - itemWidth * minButtons) / itemWidth).toFixed(2))));
		}
		const range = computed(() => {
			if (length.value <= 0 || isNaN(length.value) || length.value > Number.MAX_SAFE_INTEGER) return [];
			if (totalVisible.value <= 0) return [];
			else if (totalVisible.value === 1) return [page.value];
			if (length.value <= totalVisible.value) return createRange(length.value, start.value);
			const even = totalVisible.value % 2 === 0;
			const middle = even ? totalVisible.value / 2 : Math.floor(totalVisible.value / 2);
			const left = even ? middle : middle + 1;
			const right = length.value - middle;
			if (left - page.value >= 0) return [
				...createRange(Math.max(1, totalVisible.value - 1), start.value),
				props.ellipsis,
				length.value
			];
			else if (page.value - right >= (even ? 1 : 0)) {
				const rangeLength = totalVisible.value - 1;
				const rangeStart = length.value - rangeLength + start.value;
				return [
					start.value,
					props.ellipsis,
					...createRange(rangeLength, rangeStart)
				];
			} else {
				const rangeLength = Math.max(1, totalVisible.value - 2);
				const rangeStart = rangeLength === 1 ? page.value : page.value - Math.ceil(rangeLength / 2) + start.value;
				return [
					start.value,
					props.ellipsis,
					...createRange(rangeLength, rangeStart),
					props.ellipsis,
					length.value
				];
			}
		});
		function setValue(e, value, event) {
			e.preventDefault();
			page.value = value;
			event && emit(event, value);
		}
		const { refs, updateRef } = useRefs();
		provideDefaults({ VPaginationBtn: {
			color: toRef(() => props.color),
			border: toRef(() => props.border),
			density: toRef(() => props.density),
			size: toRef(() => props.size),
			variant: toRef(() => props.variant),
			rounded: toRef(() => props.rounded),
			elevation: toRef(() => props.elevation)
		} });
		const items = computed(() => {
			return range.value.map((item, index) => {
				const ref = (e) => updateRef(e, index);
				if (typeof item === "string") return {
					isActive: false,
					key: `ellipsis-${index}`,
					page: item,
					props: {
						ref,
						ellipsis: true,
						icon: true,
						disabled: true
					}
				};
				else {
					const isActive = item === page.value;
					return {
						isActive,
						key: item,
						page: n(item),
						props: {
							ref,
							ellipsis: false,
							icon: true,
							disabled: !!props.disabled || Number(props.length) < 2,
							color: isActive ? props.activeColor : props.color,
							"aria-current": isActive,
							"aria-label": t(isActive ? props.currentPageAriaLabel : props.pageAriaLabel, item),
							onClick: (e) => setValue(e, item)
						}
					};
				}
			});
		});
		const controls = computed(() => {
			const prevDisabled = !!props.disabled || page.value <= start.value;
			const nextDisabled = !!props.disabled || page.value >= start.value + length.value - 1;
			return {
				first: props.showFirstLastPage ? {
					icon: isRtl.value ? props.lastIcon : props.firstIcon,
					onClick: (e) => setValue(e, start.value, "first"),
					disabled: prevDisabled,
					"aria-label": t(props.firstAriaLabel),
					"aria-disabled": prevDisabled
				} : void 0,
				prev: {
					icon: isRtl.value ? props.nextIcon : props.prevIcon,
					onClick: (e) => setValue(e, page.value - 1, "prev"),
					disabled: prevDisabled,
					"aria-label": t(props.previousAriaLabel),
					"aria-disabled": prevDisabled
				},
				next: {
					icon: isRtl.value ? props.prevIcon : props.nextIcon,
					onClick: (e) => setValue(e, page.value + 1, "next"),
					disabled: nextDisabled,
					"aria-label": t(props.nextAriaLabel),
					"aria-disabled": nextDisabled
				},
				last: props.showFirstLastPage ? {
					icon: isRtl.value ? props.firstIcon : props.lastIcon,
					onClick: (e) => setValue(e, start.value + length.value - 1, "last"),
					disabled: nextDisabled,
					"aria-label": t(props.lastAriaLabel),
					"aria-disabled": nextDisabled
				} : void 0
			};
		});
		function updateFocus() {
			const currentIndex = page.value - start.value;
			refs.value[currentIndex]?.$el.focus();
		}
		function onKeydown(e) {
			if (e.key === keyValues.left && !props.disabled && page.value > Number(props.start)) {
				page.value = page.value - 1;
				nextTick(updateFocus);
			} else if (e.key === keyValues.right && !props.disabled && page.value < start.value + length.value - 1) {
				page.value = page.value + 1;
				nextTick(updateFocus);
			}
		}
		useRender(() => createVNode(props.tag, {
			"ref": resizeRef,
			"class": [
				"v-pagination",
				themeClasses.value,
				props.class
			],
			"style": props.style,
			"role": "navigation",
			"aria-label": t(props.ariaLabel),
			"onKeydown": onKeydown,
			"data-test": "v-pagination-root"
		}, { default: () => [createVNode("ul", { "class": "v-pagination__list" }, [
			props.showFirstLastPage && createVNode("li", {
				"key": "first",
				"class": "v-pagination__first",
				"data-test": "v-pagination-first"
			}, [slots.first ? slots.first(controls.value.first) : createVNode(VBtn, mergeProps({ "_as": "VPaginationBtn" }, controls.value.first), null)]),
			createVNode("li", {
				"key": "prev",
				"class": "v-pagination__prev",
				"data-test": "v-pagination-prev"
			}, [slots.prev ? slots.prev(controls.value.prev) : createVNode(VBtn, mergeProps({ "_as": "VPaginationBtn" }, controls.value.prev), null)]),
			items.value.map((item, index) => createVNode("li", {
				"key": item.key,
				"class": ["v-pagination__item", { "v-pagination__item--is-active": item.isActive }],
				"data-test": "v-pagination-item"
			}, [slots.item ? slots.item(item) : createVNode(VBtn, mergeProps({ "_as": "VPaginationBtn" }, item.props), { default: () => [item.page] })])),
			createVNode("li", {
				"key": "next",
				"class": "v-pagination__next",
				"data-test": "v-pagination-next"
			}, [slots.next ? slots.next(controls.value.next) : createVNode(VBtn, mergeProps({ "_as": "VPaginationBtn" }, controls.value.next), null)]),
			props.showFirstLastPage && createVNode("li", {
				"key": "last",
				"class": "v-pagination__last",
				"data-test": "v-pagination-last"
			}, [slots.last ? slots.last(controls.value.last) : createVNode(VBtn, mergeProps({ "_as": "VPaginationBtn" }, controls.value.last), null)])
		])] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableFooter.js
var makeVDataTableFooterProps = propsFactory({
	prevIcon: {
		type: IconValue,
		default: "$prev"
	},
	nextIcon: {
		type: IconValue,
		default: "$next"
	},
	firstIcon: {
		type: IconValue,
		default: "$first"
	},
	lastIcon: {
		type: IconValue,
		default: "$last"
	},
	itemsPerPageText: {
		type: String,
		default: "$vuetify.dataFooter.itemsPerPageText"
	},
	pageText: {
		type: String,
		default: "$vuetify.dataFooter.pageText"
	},
	firstPageLabel: {
		type: String,
		default: "$vuetify.dataFooter.firstPage"
	},
	prevPageLabel: {
		type: String,
		default: "$vuetify.dataFooter.prevPage"
	},
	nextPageLabel: {
		type: String,
		default: "$vuetify.dataFooter.nextPage"
	},
	lastPageLabel: {
		type: String,
		default: "$vuetify.dataFooter.lastPage"
	},
	itemsPerPageOptions: {
		type: Array,
		default: () => [
			{
				value: 10,
				title: "10"
			},
			{
				value: 25,
				title: "25"
			},
			{
				value: 50,
				title: "50"
			},
			{
				value: 100,
				title: "100"
			},
			{
				value: -1,
				title: "$vuetify.dataFooter.itemsPerPageAll"
			}
		]
	},
	showCurrentPage: Boolean
}, "VDataTableFooter");
var VDataTableFooter = genericComponent()({
	name: "VDataTableFooter",
	props: makeVDataTableFooterProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { t } = useLocale();
		const { page, pageCount, startIndex, stopIndex, itemsLength, itemsPerPage, setItemsPerPage } = usePagination();
		const itemsPerPageOptions = computed(() => props.itemsPerPageOptions.map((option) => {
			if (typeof option === "number") return {
				value: option,
				title: option === -1 ? t("$vuetify.dataFooter.itemsPerPageAll") : String(option)
			};
			return {
				...option,
				title: !isNaN(Number(option.title)) ? option.title : t(option.title)
			};
		}));
		useRender(() => {
			const paginationProps = VPagination.filterProps(props);
			return createVNode("div", { "class": "v-data-table-footer" }, [
				slots.prepend?.(),
				createVNode("div", { "class": "v-data-table-footer__items-per-page" }, [createVNode("span", null, [t(props.itemsPerPageText)]), createVNode(VSelect, {
					"items": itemsPerPageOptions.value,
					"modelValue": itemsPerPage.value,
					"onUpdate:modelValue": (v) => setItemsPerPage(Number(v)),
					"density": "compact",
					"variant": "outlined",
					"hide-details": true
				}, null)]),
				createVNode("div", { "class": "v-data-table-footer__info" }, [createVNode("div", null, [t(props.pageText, !itemsLength.value ? 0 : startIndex.value + 1, stopIndex.value, itemsLength.value)])]),
				createVNode("div", { "class": "v-data-table-footer__pagination" }, [createVNode(VPagination, mergeProps({
					"modelValue": page.value,
					"onUpdate:modelValue": ($event) => page.value = $event,
					"density": "comfortable",
					"first-aria-label": props.firstPageLabel,
					"last-aria-label": props.lastPageLabel,
					"length": pageCount.value,
					"next-aria-label": props.nextPageLabel,
					"previous-aria-label": props.prevPageLabel,
					"rounded": true,
					"show-first-last-page": true,
					"total-visible": props.showCurrentPage ? 1 : 0,
					"variant": "plain"
				}, paginationProps), null)])
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableColumn.js
var VDataTableColumn = defineFunctionalComponent({
	align: {
		type: String,
		default: "start"
	},
	fixed: Boolean,
	fixedOffset: [Number, String],
	height: [Number, String],
	lastFixed: Boolean,
	noPadding: Boolean,
	tag: String,
	width: [Number, String],
	maxWidth: [Number, String],
	nowrap: Boolean
}, (props, _ref) => {
	let { slots } = _ref;
	return createVNode(props.tag ?? "td", {
		"class": [
			"v-data-table__td",
			{
				"v-data-table-column--fixed": props.fixed,
				"v-data-table-column--last-fixed": props.lastFixed,
				"v-data-table-column--no-padding": props.noPadding,
				"v-data-table-column--nowrap": props.nowrap
			},
			`v-data-table-column--align-${props.align}`
		],
		"style": {
			height: convertToUnit(props.height),
			width: convertToUnit(props.width),
			maxWidth: convertToUnit(props.maxWidth),
			left: convertToUnit(props.fixedOffset || null)
		}
	}, { default: () => [slots.default?.()] });
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/headers.js
var makeDataTableHeaderProps = propsFactory({ headers: Array }, "DataTable-header");
var VDataTableHeadersSymbol = Symbol.for("vuetify:data-table-headers");
var defaultHeader = {
	title: "",
	sortable: false
};
var defaultActionHeader = {
	...defaultHeader,
	width: 48
};
function priorityQueue() {
	const queue = (arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : []).map((element) => ({
		element,
		priority: 0
	}));
	return {
		enqueue: (element, priority) => {
			let added = false;
			for (let i = 0; i < queue.length; i++) if (queue[i].priority > priority) {
				queue.splice(i, 0, {
					element,
					priority
				});
				added = true;
				break;
			}
			if (!added) queue.push({
				element,
				priority
			});
		},
		size: () => queue.length,
		count: () => {
			let count = 0;
			if (!queue.length) return 0;
			const whole = Math.floor(queue[0].priority);
			for (let i = 0; i < queue.length; i++) if (Math.floor(queue[i].priority) === whole) count += 1;
			return count;
		},
		dequeue: () => {
			return queue.shift();
		}
	};
}
function extractLeaves(item) {
	let columns = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : [];
	if (!item.children) columns.push(item);
	else for (const child of item.children) extractLeaves(child, columns);
	return columns;
}
function extractKeys(headers) {
	let keys = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : /* @__PURE__ */ new Set();
	for (const item of headers) {
		if (item.key) keys.add(item.key);
		if (item.children) extractKeys(item.children, keys);
	}
	return keys;
}
function getDefaultItem(item) {
	if (!item.key) return void 0;
	if (item.key === "data-table-group") return defaultHeader;
	if (["data-table-expand", "data-table-select"].includes(item.key)) return defaultActionHeader;
}
function getDepth(item) {
	let depth = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : 0;
	if (!item.children) return depth;
	return Math.max(depth, ...item.children.map((child) => getDepth(child, depth + 1)));
}
function parseFixedColumns(items) {
	let seenFixed = false;
	function setFixed(item) {
		let parentFixed = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : false;
		if (!item) return;
		if (parentFixed) item.fixed = true;
		if (item.fixed) if (item.children) for (let i = item.children.length - 1; i >= 0; i--) setFixed(item.children[i], true);
		else {
			if (!seenFixed) item.lastFixed = true;
			else if (isNaN(Number(item.width))) consoleError(`Multiple fixed columns should have a static width (key: ${item.key})`);
			else item.minWidth = Math.max(Number(item.width) || 0, Number(item.minWidth) || 0);
			seenFixed = true;
		}
		else if (item.children) for (let i = item.children.length - 1; i >= 0; i--) setFixed(item.children[i]);
		else seenFixed = false;
	}
	for (let i = items.length - 1; i >= 0; i--) setFixed(items[i]);
	function setFixedOffset(item) {
		let fixedOffset = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : 0;
		if (!item) return fixedOffset;
		if (item.children) {
			item.fixedOffset = fixedOffset;
			for (const child of item.children) fixedOffset = setFixedOffset(child, fixedOffset);
		} else if (item.fixed) {
			item.fixedOffset = fixedOffset;
			fixedOffset += parseFloat(item.width || "0") || 0;
		}
		return fixedOffset;
	}
	let fixedOffset = 0;
	for (const item of items) fixedOffset = setFixedOffset(item, fixedOffset);
}
function parse(items, maxDepth) {
	const headers = [];
	let currentDepth = 0;
	const queue = priorityQueue(items);
	while (queue.size() > 0) {
		let rowSize = queue.count();
		const row = [];
		let fraction = 1;
		while (rowSize > 0) {
			const { element: item, priority } = queue.dequeue();
			const diff = maxDepth - currentDepth - getDepth(item);
			row.push({
				...item,
				rowspan: diff ?? 1,
				colspan: item.children ? extractLeaves(item).length : 1
			});
			if (item.children) for (const child of item.children) {
				const sort = priority % 1 + fraction / Math.pow(10, currentDepth + 2);
				queue.enqueue(child, currentDepth + diff + sort);
			}
			fraction += 1;
			rowSize -= 1;
		}
		currentDepth += 1;
		headers.push(row);
	}
	return {
		columns: items.map((item) => extractLeaves(item)).flat(),
		headers
	};
}
function convertToInternalHeaders(items) {
	const internalHeaders = [];
	for (const item of items) {
		const defaultItem = {
			...getDefaultItem(item),
			...item
		};
		const key = defaultItem.key ?? (typeof defaultItem.value === "string" ? defaultItem.value : null);
		const value = defaultItem.value ?? key ?? null;
		const internalItem = {
			...defaultItem,
			key,
			value,
			sortable: defaultItem.sortable ?? (defaultItem.key != null || !!defaultItem.sort),
			children: defaultItem.children ? convertToInternalHeaders(defaultItem.children) : void 0
		};
		internalHeaders.push(internalItem);
	}
	return internalHeaders;
}
function createHeaders(props, options) {
	const headers = ref([]);
	const columns = ref([]);
	const sortFunctions = ref({});
	const sortRawFunctions = ref({});
	const filterFunctions = ref({});
	watchEffect(() => {
		const items = (props.headers || Object.keys(props.items[0] ?? {}).map((key) => ({
			key,
			title: capitalize(key)
		}))).slice();
		const keys = extractKeys(items);
		if (options?.groupBy?.value.length && !keys.has("data-table-group")) items.unshift({
			key: "data-table-group",
			title: "Group"
		});
		if (options?.showSelect?.value && !keys.has("data-table-select")) items.unshift({ key: "data-table-select" });
		if (options?.showExpand?.value && !keys.has("data-table-expand")) items.push({ key: "data-table-expand" });
		const internalHeaders = convertToInternalHeaders(items);
		parseFixedColumns(internalHeaders);
		const parsed = parse(internalHeaders, Math.max(...internalHeaders.map((item) => getDepth(item))) + 1);
		headers.value = parsed.headers;
		columns.value = parsed.columns;
		const flatHeaders = parsed.headers.flat(1);
		for (const header of flatHeaders) {
			if (!header.key) continue;
			if (header.sortable) {
				if (header.sort) sortFunctions.value[header.key] = header.sort;
				if (header.sortRaw) sortRawFunctions.value[header.key] = header.sortRaw;
			}
			if (header.filter) filterFunctions.value[header.key] = header.filter;
		}
	});
	const data = {
		headers,
		columns,
		sortFunctions,
		sortRawFunctions,
		filterFunctions
	};
	provide(VDataTableHeadersSymbol, data);
	return data;
}
function useHeaders() {
	const data = inject(VDataTableHeadersSymbol);
	if (!data) throw new Error("Missing headers!");
	return data;
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableHeaders.js
var makeVDataTableHeadersProps = propsFactory({
	color: String,
	disableSort: Boolean,
	fixedHeader: Boolean,
	multiSort: Boolean,
	sortAscIcon: {
		type: IconValue,
		default: "$sortAsc"
	},
	sortDescIcon: {
		type: IconValue,
		default: "$sortDesc"
	},
	headerProps: { type: Object },
	/** @deprecated */
	sticky: Boolean,
	...makeDisplayProps(),
	...makeLoaderProps()
}, "VDataTableHeaders");
var VDataTableHeaders = genericComponent()({
	name: "VDataTableHeaders",
	props: makeVDataTableHeadersProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { t } = useLocale();
		const { toggleSort, sortBy, isSorted } = useSort();
		const { someSelected, allSelected, selectAll, showSelectAll } = useSelection();
		const { columns, headers } = useHeaders();
		const { loaderClasses } = useLoader(props);
		function getFixedStyles(column, y) {
			if (!(props.sticky || props.fixedHeader) && !column.fixed) return void 0;
			return {
				position: "sticky",
				left: column.fixed ? convertToUnit(column.fixedOffset) : void 0,
				top: props.sticky || props.fixedHeader ? `calc(var(--v-table-header-height) * ${y})` : void 0
			};
		}
		function getSortIcon(column) {
			const item = sortBy.value.find((item) => item.key === column.key);
			if (!item) return props.sortAscIcon;
			return item.order === "asc" ? props.sortAscIcon : props.sortDescIcon;
		}
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { displayClasses, mobile } = useDisplay(props);
		const slotProps = computed(() => ({
			headers: headers.value,
			columns: columns.value,
			toggleSort,
			isSorted,
			sortBy: sortBy.value,
			someSelected: someSelected.value,
			allSelected: allSelected.value,
			selectAll,
			getSortIcon
		}));
		const headerCellClasses = computed(() => [
			"v-data-table__th",
			{ "v-data-table__th--sticky": props.sticky || props.fixedHeader },
			displayClasses.value,
			loaderClasses.value
		]);
		const VDataTableHeaderCell = (_ref2) => {
			let { column, x, y } = _ref2;
			const noPadding = column.key === "data-table-select" || column.key === "data-table-expand";
			const headerProps = mergeProps(props.headerProps ?? {}, column.headerProps ?? {});
			return createVNode(VDataTableColumn, mergeProps({
				"tag": "th",
				"align": column.align,
				"class": [{
					"v-data-table__th--sortable": column.sortable && !props.disableSort,
					"v-data-table__th--sorted": isSorted(column),
					"v-data-table__th--fixed": column.fixed
				}, ...headerCellClasses.value],
				"style": {
					width: convertToUnit(column.width),
					minWidth: convertToUnit(column.minWidth),
					maxWidth: convertToUnit(column.maxWidth),
					...getFixedStyles(column, y)
				},
				"colspan": column.colspan,
				"rowspan": column.rowspan,
				"onClick": column.sortable ? () => toggleSort(column) : void 0,
				"fixed": column.fixed,
				"nowrap": column.nowrap,
				"lastFixed": column.lastFixed,
				"noPadding": noPadding
			}, headerProps), { default: () => {
				const columnSlotName = `header.${column.key}`;
				const columnSlotProps = {
					column,
					selectAll,
					isSorted,
					toggleSort,
					sortBy: sortBy.value,
					someSelected: someSelected.value,
					allSelected: allSelected.value,
					getSortIcon
				};
				if (slots[columnSlotName]) return slots[columnSlotName](columnSlotProps);
				if (column.key === "data-table-select") return slots["header.data-table-select"]?.(columnSlotProps) ?? (showSelectAll.value && createVNode(VCheckboxBtn, {
					"modelValue": allSelected.value,
					"indeterminate": someSelected.value && !allSelected.value,
					"onUpdate:modelValue": selectAll
				}, null));
				return createVNode("div", { "class": "v-data-table-header__content" }, [
					createVNode("span", null, [column.title]),
					column.sortable && !props.disableSort && createVNode(VIcon, {
						"key": "icon",
						"class": "v-data-table-header__sort-icon",
						"icon": getSortIcon(column)
					}, null),
					props.multiSort && isSorted(column) && createVNode("div", {
						"key": "badge",
						"class": ["v-data-table-header__sort-badge", ...backgroundColorClasses.value],
						"style": backgroundColorStyles.value
					}, [sortBy.value.findIndex((x) => x.key === column.key) + 1])
				]);
			} });
		};
		const VDataTableMobileHeaderCell = () => {
			const displayItems = computed(() => {
				return columns.value.filter((column) => column?.sortable && !props.disableSort);
			});
			const appendIcon = computed(() => {
				if (columns.value.find((column) => column.key === "data-table-select") == null) return;
				return allSelected.value ? "$checkboxOn" : someSelected.value ? "$checkboxIndeterminate" : "$checkboxOff";
			});
			return createVNode(VDataTableColumn, mergeProps({
				"tag": "th",
				"class": [...headerCellClasses.value],
				"colspan": headers.value.length + 1
			}, props.headerProps), { default: () => [createVNode("div", { "class": "v-data-table-header__content" }, [createVNode(VSelect, {
				"chips": true,
				"class": "v-data-table__td-sort-select",
				"clearable": true,
				"density": "default",
				"items": displayItems.value,
				"label": t("$vuetify.dataTable.sortBy"),
				"multiple": props.multiSort,
				"variant": "underlined",
				"onClick:clear": () => sortBy.value = [],
				"appendIcon": appendIcon.value,
				"onClick:append": () => selectAll(!allSelected.value)
			}, {
				...slots,
				chip: (props) => createVNode(VChip, {
					"onClick": props.item.raw?.sortable ? () => toggleSort(props.item.raw) : void 0,
					"onMousedown": (e) => {
						e.preventDefault();
						e.stopPropagation();
					}
				}, { default: () => [props.item.title, createVNode(VIcon, {
					"class": ["v-data-table__td-sort-icon", isSorted(props.item.raw) && "v-data-table__td-sort-icon-active"],
					"icon": getSortIcon(props.item.raw),
					"size": "small"
				}, null)] })
			})])] });
		};
		useRender(() => {
			return mobile.value ? createVNode("tr", null, [createVNode(VDataTableMobileHeaderCell, null, null)]) : createVNode(Fragment, null, [slots.headers ? slots.headers(slotProps.value) : headers.value.map((row, y) => createVNode("tr", null, [row.map((column, x) => createVNode(VDataTableHeaderCell, {
				"column": column,
				"x": x,
				"y": y
			}, null))])), props.loading && createVNode("tr", { "class": "v-data-table-progress" }, [createVNode("th", { "colspan": columns.value.length }, [createVNode(LoaderSlot, {
				"name": "v-data-table-progress",
				"absolute": true,
				"active": true,
				"color": typeof props.loading === "boolean" ? void 0 : props.loading,
				"indeterminate": true
			}, { default: slots.loader })])])]);
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableGroupHeaderRow.js
var makeVDataTableGroupHeaderRowProps = propsFactory({ item: {
	type: Object,
	required: true
} }, "VDataTableGroupHeaderRow");
var VDataTableGroupHeaderRow = genericComponent()({
	name: "VDataTableGroupHeaderRow",
	props: makeVDataTableGroupHeaderRowProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { isGroupOpen, toggleGroup, extractRows } = useGroupBy();
		const { isSelected, isSomeSelected, select } = useSelection();
		const { columns } = useHeaders();
		const rows = computed(() => {
			return extractRows([props.item]);
		});
		return () => createVNode("tr", {
			"class": "v-data-table-group-header-row",
			"style": { "--v-data-table-group-header-row-depth": props.item.depth }
		}, [columns.value.map((column) => {
			if (column.key === "data-table-group") {
				const icon = isGroupOpen(props.item) ? "$expand" : "$next";
				const onClick = () => toggleGroup(props.item);
				return slots["data-table-group"]?.({
					item: props.item,
					count: rows.value.length,
					props: {
						icon,
						onClick
					}
				}) ?? createVNode(VDataTableColumn, { "class": "v-data-table-group-header-row__column" }, { default: () => [
					createVNode(VBtn, {
						"size": "small",
						"variant": "text",
						"icon": icon,
						"onClick": onClick
					}, null),
					createVNode("span", null, [props.item.value]),
					createVNode("span", null, [
						createTextVNode("("),
						rows.value.length,
						createTextVNode(")")
					])
				] });
			}
			if (column.key === "data-table-select") {
				const modelValue = isSelected(rows.value);
				const indeterminate = isSomeSelected(rows.value) && !modelValue;
				const selectGroup = (v) => select(rows.value, v);
				return slots["data-table-select"]?.({ props: {
					modelValue,
					indeterminate,
					"onUpdate:modelValue": selectGroup
				} }) ?? createVNode("td", null, [createVNode(VCheckboxBtn, {
					"modelValue": modelValue,
					"indeterminate": indeterminate,
					"onUpdate:modelValue": selectGroup
				}, null)]);
			}
			return createVNode("td", null, null);
		})]);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableRow.js
var makeVDataTableRowProps = propsFactory({
	index: Number,
	item: Object,
	cellProps: [Object, Function],
	onClick: EventProp(),
	onContextmenu: EventProp(),
	onDblclick: EventProp(),
	...makeDisplayProps()
}, "VDataTableRow");
var VDataTableRow = genericComponent()({
	name: "VDataTableRow",
	props: makeVDataTableRowProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { displayClasses, mobile } = useDisplay(props, "v-data-table__tr");
		const { isSelected, toggleSelect, someSelected, allSelected, selectAll } = useSelection();
		const { isExpanded, toggleExpand } = useExpanded();
		const { toggleSort, sortBy, isSorted } = useSort();
		const { columns } = useHeaders();
		useRender(() => createVNode("tr", {
			"class": [
				"v-data-table__tr",
				{ "v-data-table__tr--clickable": !!(props.onClick || props.onContextmenu || props.onDblclick) },
				displayClasses.value
			],
			"onClick": props.onClick,
			"onContextmenu": props.onContextmenu,
			"onDblclick": props.onDblclick
		}, [props.item && columns.value.map((column, i) => {
			const item = props.item;
			const slotName = `item.${column.key}`;
			const headerSlotName = `header.${column.key}`;
			const slotProps = {
				index: props.index,
				item: item.raw,
				internalItem: item,
				value: getObjectValueByPath(item.columns, column.key),
				column,
				isSelected,
				toggleSelect,
				isExpanded,
				toggleExpand
			};
			const columnSlotProps = {
				column,
				selectAll,
				isSorted,
				toggleSort,
				sortBy: sortBy.value,
				someSelected: someSelected.value,
				allSelected: allSelected.value,
				getSortIcon: () => ""
			};
			const cellProps = typeof props.cellProps === "function" ? props.cellProps({
				index: slotProps.index,
				item: slotProps.item,
				internalItem: slotProps.internalItem,
				value: slotProps.value,
				column
			}) : props.cellProps;
			const columnCellProps = typeof column.cellProps === "function" ? column.cellProps({
				index: slotProps.index,
				item: slotProps.item,
				internalItem: slotProps.internalItem,
				value: slotProps.value
			}) : column.cellProps;
			return createVNode(VDataTableColumn, mergeProps({
				"align": column.align,
				"class": {
					"v-data-table__td--expanded-row": column.key === "data-table-expand",
					"v-data-table__td--select-row": column.key === "data-table-select"
				},
				"fixed": column.fixed,
				"fixedOffset": column.fixedOffset,
				"lastFixed": column.lastFixed,
				"maxWidth": !mobile.value ? column.maxWidth : void 0,
				"noPadding": column.key === "data-table-select" || column.key === "data-table-expand",
				"nowrap": column.nowrap,
				"width": !mobile.value ? column.width : void 0
			}, cellProps, columnCellProps), { default: () => {
				if (column.key === "data-table-select") return slots["item.data-table-select"]?.({
					...slotProps,
					props: {
						disabled: !item.selectable,
						modelValue: isSelected([item]),
						onClick: withModifiers(() => toggleSelect(item), ["stop"])
					}
				}) ?? createVNode(VCheckboxBtn, {
					"disabled": !item.selectable,
					"modelValue": isSelected([item]),
					"onClick": withModifiers((event) => toggleSelect(item, props.index, event), ["stop"])
				}, null);
				if (column.key === "data-table-expand") return slots["item.data-table-expand"]?.({
					...slotProps,
					props: {
						icon: isExpanded(item) ? "$collapse" : "$expand",
						size: "small",
						variant: "text",
						onClick: withModifiers(() => toggleExpand(item), ["stop"])
					}
				}) ?? createVNode(VBtn, {
					"icon": isExpanded(item) ? "$collapse" : "$expand",
					"size": "small",
					"variant": "text",
					"onClick": withModifiers(() => toggleExpand(item), ["stop"])
				}, null);
				if (slots[slotName] && !mobile.value) return slots[slotName](slotProps);
				const displayValue = toDisplayString(slotProps.value);
				return !mobile.value ? displayValue : createVNode(Fragment, null, [createVNode("div", { "class": "v-data-table__td-title" }, [slots[headerSlotName]?.(columnSlotProps) ?? column.title]), createVNode("div", { "class": "v-data-table__td-value" }, [slots[slotName]?.(slotProps) ?? displayValue])]);
			} });
		})]));
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableRows.js
var makeVDataTableRowsProps = propsFactory({
	loading: [Boolean, String],
	loadingText: {
		type: String,
		default: "$vuetify.dataIterator.loadingText"
	},
	hideNoData: Boolean,
	items: {
		type: Array,
		default: () => []
	},
	noDataText: {
		type: String,
		default: "$vuetify.noDataText"
	},
	rowProps: [Object, Function],
	cellProps: [Object, Function],
	...makeDisplayProps()
}, "VDataTableRows");
var VDataTableRows = genericComponent()({
	name: "VDataTableRows",
	inheritAttrs: false,
	props: makeVDataTableRowsProps(),
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { columns } = useHeaders();
		const { expandOnClick, toggleExpand, isExpanded } = useExpanded();
		const { isSelected, toggleSelect } = useSelection();
		const { toggleGroup, isGroupOpen } = useGroupBy();
		const { t } = useLocale();
		const { mobile } = useDisplay(props);
		useRender(() => {
			if (props.loading && (!props.items.length || slots.loading)) return createVNode("tr", {
				"class": "v-data-table-rows-loading",
				"key": "loading"
			}, [createVNode("td", { "colspan": columns.value.length }, [slots.loading?.() ?? t(props.loadingText)])]);
			if (!props.loading && !props.items.length && !props.hideNoData) return createVNode("tr", {
				"class": "v-data-table-rows-no-data",
				"key": "no-data"
			}, [createVNode("td", { "colspan": columns.value.length }, [slots["no-data"]?.() ?? t(props.noDataText)])]);
			return createVNode(Fragment, null, [props.items.map((item, index) => {
				if (item.type === "group") {
					const slotProps = {
						index,
						item,
						columns: columns.value,
						isExpanded,
						toggleExpand,
						isSelected,
						toggleSelect,
						toggleGroup,
						isGroupOpen
					};
					return slots["group-header"] ? slots["group-header"](slotProps) : createVNode(VDataTableGroupHeaderRow, mergeProps({
						"key": `group-header_${item.id}`,
						"item": item
					}, getPrefixedEventHandlers(attrs, ":group-header", () => slotProps)), slots);
				}
				const slotProps = {
					index,
					item: item.raw,
					internalItem: item,
					columns: columns.value,
					isExpanded,
					toggleExpand,
					isSelected,
					toggleSelect
				};
				const itemSlotProps = {
					...slotProps,
					props: mergeProps({
						key: `item_${item.key ?? item.index}`,
						onClick: expandOnClick.value ? () => {
							toggleExpand(item);
						} : void 0,
						index,
						item,
						cellProps: props.cellProps,
						mobile: mobile.value
					}, getPrefixedEventHandlers(attrs, ":row", () => slotProps), typeof props.rowProps === "function" ? props.rowProps({
						item: slotProps.item,
						index: slotProps.index,
						internalItem: slotProps.internalItem
					}) : props.rowProps)
				};
				return createVNode(Fragment, { "key": itemSlotProps.props.key }, [slots.item ? slots.item(itemSlotProps) : createVNode(VDataTableRow, itemSlotProps.props, slots), isExpanded(item) && slots["expanded-row"]?.(slotProps)]);
			})]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTable/VTable.js
var makeVTableProps = propsFactory({
	fixedHeader: Boolean,
	fixedFooter: Boolean,
	height: [Number, String],
	hover: Boolean,
	...makeComponentProps(),
	...makeDensityProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VTable");
var VTable = genericComponent()({
	name: "VTable",
	props: makeVTableProps(),
	setup(props, _ref) {
		let { slots, emit } = _ref;
		const { themeClasses } = provideTheme(props);
		const { densityClasses } = useDensity(props);
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-table",
				{
					"v-table--fixed-height": !!props.height,
					"v-table--fixed-header": props.fixedHeader,
					"v-table--fixed-footer": props.fixedFooter,
					"v-table--has-top": !!slots.top,
					"v-table--has-bottom": !!slots.bottom,
					"v-table--hover": props.hover
				},
				themeClasses.value,
				densityClasses.value,
				props.class
			],
			"style": props.style
		}, { default: () => [
			slots.top?.(),
			slots.default ? createVNode("div", {
				"class": "v-table__wrapper",
				"style": { height: convertToUnit(props.height) }
			}, [createVNode("table", null, [slots.default()])]) : slots.wrapper?.(),
			slots.bottom?.()
		] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/composables/items.js
var makeDataTableItemsProps = propsFactory({
	items: {
		type: Array,
		default: () => []
	},
	itemValue: {
		type: [
			String,
			Array,
			Function
		],
		default: "id"
	},
	itemSelectable: {
		type: [
			String,
			Array,
			Function
		],
		default: null
	},
	rowProps: [Object, Function],
	cellProps: [Object, Function],
	returnObject: Boolean
}, "DataTable-items");
function transformItem(props, item, index, columns) {
	const value = props.returnObject ? item : getPropertyFromItem(item, props.itemValue);
	const selectable = getPropertyFromItem(item, props.itemSelectable, true);
	const itemColumns = columns.reduce((obj, column) => {
		if (column.key != null) obj[column.key] = getPropertyFromItem(item, column.value);
		return obj;
	}, {});
	return {
		type: "item",
		key: props.returnObject ? getPropertyFromItem(item, props.itemValue) : value,
		index,
		value,
		selectable,
		columns: itemColumns,
		raw: item
	};
}
function transformItems(props, items, columns) {
	return items.map((item, index) => transformItem(props, item, index, columns));
}
function useDataTableItems(props, columns) {
	return { items: computed(() => transformItems(props, props.items, columns.value)) };
}
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTable.js
var makeDataTableProps = propsFactory({
	...makeVDataTableRowsProps(),
	hideDefaultBody: Boolean,
	hideDefaultFooter: Boolean,
	hideDefaultHeader: Boolean,
	width: [String, Number],
	search: String,
	...makeDataTableExpandProps(),
	...makeDataTableGroupProps(),
	...makeDataTableHeaderProps(),
	...makeDataTableItemsProps(),
	...makeDataTableSelectProps(),
	...makeDataTableSortProps(),
	...makeVDataTableHeadersProps(),
	...makeVTableProps()
}, "DataTable");
var makeVDataTableProps = propsFactory({
	...makeDataTablePaginateProps(),
	...makeDataTableProps(),
	...makeFilterProps(),
	...makeVDataTableFooterProps()
}, "VDataTable");
var VDataTable = genericComponent()({
	name: "VDataTable",
	props: makeVDataTableProps(),
	emits: {
		"update:modelValue": (value) => true,
		"update:page": (value) => true,
		"update:itemsPerPage": (value) => true,
		"update:sortBy": (value) => true,
		"update:options": (value) => true,
		"update:groupBy": (value) => true,
		"update:expanded": (value) => true,
		"update:currentItems": (value) => true
	},
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { groupBy } = createGroupBy(props);
		const { sortBy, multiSort, mustSort } = createSort(props);
		const { page, itemsPerPage } = createPagination(props);
		const { disableSort } = toRefs(props);
		const { columns, headers, sortFunctions, sortRawFunctions, filterFunctions } = createHeaders(props, {
			groupBy,
			showSelect: toRef(() => props.showSelect),
			showExpand: toRef(() => props.showExpand)
		});
		const { items } = useDataTableItems(props, columns);
		const search = toRef(() => props.search);
		const { filteredItems } = useFilter(props, items, search, {
			transform: (item) => item.columns,
			customKeyFilter: filterFunctions
		});
		const { toggleSort } = provideSort({
			sortBy,
			multiSort,
			mustSort,
			page
		});
		const { sortByWithGroups, opened, extractRows, isGroupOpen, toggleGroup } = provideGroupBy({
			groupBy,
			sortBy,
			disableSort
		});
		const { sortedItems } = useSortedItems(props, filteredItems, sortByWithGroups, {
			transform: (item) => ({
				...item.raw,
				...item.columns
			}),
			sortFunctions,
			sortRawFunctions
		});
		const { flatItems } = useGroupedItems(sortedItems, groupBy, opened);
		const { startIndex, stopIndex, pageCount, setItemsPerPage } = providePagination({
			page,
			itemsPerPage,
			itemsLength: computed(() => flatItems.value.length)
		});
		const { paginatedItems } = usePaginatedItems({
			items: flatItems,
			startIndex,
			stopIndex,
			itemsPerPage
		});
		const paginatedItemsWithoutGroups = computed(() => extractRows(paginatedItems.value));
		const { isSelected, select, selectAll, toggleSelect, someSelected, allSelected } = provideSelection(props, {
			allItems: items,
			currentPage: paginatedItemsWithoutGroups
		});
		const { isExpanded, toggleExpand } = provideExpanded(props);
		useOptions({
			page,
			itemsPerPage,
			sortBy,
			groupBy,
			search
		});
		provideDefaults({ VDataTableRows: {
			hideNoData: toRef(() => props.hideNoData),
			noDataText: toRef(() => props.noDataText),
			loading: toRef(() => props.loading),
			loadingText: toRef(() => props.loadingText)
		} });
		const slotProps = computed(() => ({
			page: page.value,
			itemsPerPage: itemsPerPage.value,
			sortBy: sortBy.value,
			pageCount: pageCount.value,
			toggleSort,
			setItemsPerPage,
			someSelected: someSelected.value,
			allSelected: allSelected.value,
			isSelected,
			select,
			selectAll,
			toggleSelect,
			isExpanded,
			toggleExpand,
			isGroupOpen,
			toggleGroup,
			items: paginatedItemsWithoutGroups.value.map((item) => item.raw),
			internalItems: paginatedItemsWithoutGroups.value,
			groupedItems: paginatedItems.value,
			columns: columns.value,
			headers: headers.value
		}));
		useRender(() => {
			const dataTableFooterProps = VDataTableFooter.filterProps(props);
			const dataTableHeadersProps = VDataTableHeaders.filterProps(props);
			const dataTableRowsProps = VDataTableRows.filterProps(props);
			const tableProps = VTable.filterProps(props);
			return createVNode(VTable, mergeProps({
				"class": [
					"v-data-table",
					{
						"v-data-table--show-select": props.showSelect,
						"v-data-table--loading": props.loading
					},
					props.class
				],
				"style": props.style
			}, tableProps, { "fixedHeader": props.fixedHeader || props.sticky }), {
				top: () => slots.top?.(slotProps.value),
				default: () => slots.default ? slots.default(slotProps.value) : createVNode(Fragment, null, [
					slots.colgroup?.(slotProps.value),
					!props.hideDefaultHeader && createVNode("thead", { "key": "thead" }, [createVNode(VDataTableHeaders, dataTableHeadersProps, slots)]),
					slots.thead?.(slotProps.value),
					!props.hideDefaultBody && createVNode("tbody", null, [
						slots["body.prepend"]?.(slotProps.value),
						slots.body ? slots.body(slotProps.value) : createVNode(VDataTableRows, mergeProps(attrs, dataTableRowsProps, { "items": paginatedItems.value }), slots),
						slots["body.append"]?.(slotProps.value)
					]),
					slots.tbody?.(slotProps.value),
					slots.tfoot?.(slotProps.value)
				]),
				bottom: () => slots.bottom ? slots.bottom(slotProps.value) : !props.hideDefaultFooter && createVNode(Fragment, null, [createVNode(VDivider, null, null), createVNode(VDataTableFooter, dataTableFooterProps, { prepend: slots["footer.prepend"] })])
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableVirtual.js
var makeVDataTableVirtualProps = propsFactory({
	...omit(makeDataTableProps(), ["hideDefaultFooter"]),
	...makeDataTableGroupProps(),
	...makeVirtualProps(),
	...makeFilterProps()
}, "VDataTableVirtual");
var VDataTableVirtual = genericComponent()({
	name: "VDataTableVirtual",
	props: makeVDataTableVirtualProps(),
	emits: {
		"update:modelValue": (value) => true,
		"update:sortBy": (value) => true,
		"update:options": (value) => true,
		"update:groupBy": (value) => true,
		"update:expanded": (value) => true
	},
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { groupBy } = createGroupBy(props);
		const { sortBy, multiSort, mustSort } = createSort(props);
		const { disableSort } = toRefs(props);
		const { columns, headers, filterFunctions, sortFunctions, sortRawFunctions } = createHeaders(props, {
			groupBy,
			showSelect: toRef(() => props.showSelect),
			showExpand: toRef(() => props.showExpand)
		});
		const { items } = useDataTableItems(props, columns);
		const search = toRef(() => props.search);
		const { filteredItems } = useFilter(props, items, search, {
			transform: (item) => item.columns,
			customKeyFilter: filterFunctions
		});
		const { toggleSort } = provideSort({
			sortBy,
			multiSort,
			mustSort
		});
		const { sortByWithGroups, opened, extractRows, isGroupOpen, toggleGroup } = provideGroupBy({
			groupBy,
			sortBy,
			disableSort
		});
		const { sortedItems } = useSortedItems(props, filteredItems, sortByWithGroups, {
			transform: (item) => ({
				...item.raw,
				...item.columns
			}),
			sortFunctions,
			sortRawFunctions
		});
		const { flatItems } = useGroupedItems(sortedItems, groupBy, opened);
		const allItems = computed(() => extractRows(flatItems.value));
		const { isSelected, select, selectAll, toggleSelect, someSelected, allSelected } = provideSelection(props, {
			allItems,
			currentPage: allItems
		});
		const { isExpanded, toggleExpand } = provideExpanded(props);
		const { containerRef, markerRef, paddingTop, paddingBottom, computedItems, handleItemResize, handleScroll, handleScrollend, calculateVisibleItems, scrollToIndex } = useVirtual(props, flatItems);
		const displayItems = computed(() => computedItems.value.map((item) => item.raw));
		useOptions({
			sortBy,
			page: shallowRef(1),
			itemsPerPage: shallowRef(-1),
			groupBy,
			search
		});
		provideDefaults({ VDataTableRows: {
			hideNoData: toRef(() => props.hideNoData),
			noDataText: toRef(() => props.noDataText),
			loading: toRef(() => props.loading),
			loadingText: toRef(() => props.loadingText)
		} });
		const slotProps = computed(() => ({
			sortBy: sortBy.value,
			toggleSort,
			someSelected: someSelected.value,
			allSelected: allSelected.value,
			isSelected,
			select,
			selectAll,
			toggleSelect,
			isExpanded,
			toggleExpand,
			isGroupOpen,
			toggleGroup,
			items: allItems.value.map((item) => item.raw),
			internalItems: allItems.value,
			groupedItems: flatItems.value,
			columns: columns.value,
			headers: headers.value
		}));
		useRender(() => {
			const dataTableHeadersProps = VDataTableHeaders.filterProps(props);
			const dataTableRowsProps = VDataTableRows.filterProps(props);
			const tableProps = VTable.filterProps(props);
			return createVNode(VTable, mergeProps({
				"class": [
					"v-data-table",
					{ "v-data-table--loading": props.loading },
					props.class
				],
				"style": props.style
			}, tableProps, { "fixedHeader": props.fixedHeader || props.sticky }), {
				top: () => slots.top?.(slotProps.value),
				wrapper: () => createVNode("div", {
					"ref": containerRef,
					"onScrollPassive": handleScroll,
					"onScrollend": handleScrollend,
					"class": "v-table__wrapper",
					"style": { height: convertToUnit(props.height) }
				}, [createVNode("table", null, [
					slots.colgroup?.(slotProps.value),
					!props.hideDefaultHeader && createVNode("thead", { "key": "thead" }, [createVNode(VDataTableHeaders, dataTableHeadersProps, slots)]),
					slots.thead?.(slotProps.value),
					!props.hideDefaultBody && createVNode("tbody", { "key": "tbody" }, [
						createVNode("tr", {
							"ref": markerRef,
							"style": {
								height: convertToUnit(paddingTop.value),
								border: 0
							}
						}, [createVNode("td", {
							"colspan": columns.value.length,
							"style": {
								height: 0,
								border: 0
							}
						}, null)]),
						slots["body.prepend"]?.(slotProps.value),
						createVNode(VDataTableRows, mergeProps(attrs, dataTableRowsProps, { "items": displayItems.value }), {
							...slots,
							item: (itemSlotProps) => createVNode(VVirtualScrollItem, {
								"key": itemSlotProps.internalItem.index,
								"renderless": true,
								"onUpdate:height": (height) => handleItemResize(itemSlotProps.internalItem.index, height)
							}, { default: (_ref2) => {
								let { itemRef } = _ref2;
								return slots.item?.({
									...itemSlotProps,
									itemRef
								}) ?? createVNode(VDataTableRow, mergeProps(itemSlotProps.props, {
									"ref": itemRef,
									"key": itemSlotProps.internalItem.index,
									"index": itemSlotProps.internalItem.index
								}), slots);
							} })
						}),
						slots["body.append"]?.(slotProps.value),
						createVNode("tr", { "style": {
							height: convertToUnit(paddingBottom.value),
							border: 0
						} }, [createVNode("td", {
							"colspan": columns.value.length,
							"style": {
								height: 0,
								border: 0
							}
						}, null)])
					]),
					slots.tbody?.(slotProps.value),
					slots.tfoot?.(slotProps.value)
				])]),
				bottom: () => slots.bottom?.(slotProps.value)
			});
		});
		return {
			calculateVisibleItems,
			scrollToIndex
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDataTable/VDataTableServer.js
var makeVDataTableServerProps = propsFactory({
	itemsLength: {
		type: [Number, String],
		required: true
	},
	...makeDataTablePaginateProps(),
	...makeDataTableProps(),
	...makeVDataTableFooterProps()
}, "VDataTableServer");
var VDataTableServer = genericComponent()({
	name: "VDataTableServer",
	props: makeVDataTableServerProps(),
	emits: {
		"update:modelValue": (value) => true,
		"update:page": (page) => true,
		"update:itemsPerPage": (page) => true,
		"update:sortBy": (sortBy) => true,
		"update:options": (options) => true,
		"update:expanded": (options) => true,
		"update:groupBy": (value) => true
	},
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const { groupBy } = createGroupBy(props);
		const { sortBy, multiSort, mustSort } = createSort(props);
		const { page, itemsPerPage } = createPagination(props);
		const { disableSort } = toRefs(props);
		const itemsLength = computed(() => parseInt(props.itemsLength, 10));
		const { columns, headers } = createHeaders(props, {
			groupBy,
			showSelect: toRef(() => props.showSelect),
			showExpand: toRef(() => props.showExpand)
		});
		const { items } = useDataTableItems(props, columns);
		const { toggleSort } = provideSort({
			sortBy,
			multiSort,
			mustSort,
			page
		});
		const { opened, isGroupOpen, toggleGroup, extractRows } = provideGroupBy({
			groupBy,
			sortBy,
			disableSort
		});
		const { pageCount, setItemsPerPage } = providePagination({
			page,
			itemsPerPage,
			itemsLength
		});
		const { flatItems } = useGroupedItems(items, groupBy, opened);
		const { isSelected, select, selectAll, toggleSelect, someSelected, allSelected } = provideSelection(props, {
			allItems: items,
			currentPage: items
		});
		const { isExpanded, toggleExpand } = provideExpanded(props);
		const itemsWithoutGroups = computed(() => extractRows(items.value));
		useOptions({
			page,
			itemsPerPage,
			sortBy,
			groupBy,
			search: toRef(() => props.search)
		});
		provide("v-data-table", {
			toggleSort,
			sortBy
		});
		provideDefaults({ VDataTableRows: {
			hideNoData: toRef(() => props.hideNoData),
			noDataText: toRef(() => props.noDataText),
			loading: toRef(() => props.loading),
			loadingText: toRef(() => props.loadingText)
		} });
		const slotProps = computed(() => ({
			page: page.value,
			itemsPerPage: itemsPerPage.value,
			sortBy: sortBy.value,
			pageCount: pageCount.value,
			toggleSort,
			setItemsPerPage,
			someSelected: someSelected.value,
			allSelected: allSelected.value,
			isSelected,
			select,
			selectAll,
			toggleSelect,
			isExpanded,
			toggleExpand,
			isGroupOpen,
			toggleGroup,
			items: itemsWithoutGroups.value.map((item) => item.raw),
			internalItems: itemsWithoutGroups.value,
			groupedItems: flatItems.value,
			columns: columns.value,
			headers: headers.value
		}));
		useRender(() => {
			const dataTableFooterProps = VDataTableFooter.filterProps(props);
			const dataTableHeadersProps = VDataTableHeaders.filterProps(props);
			const dataTableRowsProps = VDataTableRows.filterProps(props);
			const tableProps = VTable.filterProps(props);
			return createVNode(VTable, mergeProps({
				"class": [
					"v-data-table",
					{ "v-data-table--loading": props.loading },
					props.class
				],
				"style": props.style
			}, tableProps, { "fixedHeader": props.fixedHeader || props.sticky }), {
				top: () => slots.top?.(slotProps.value),
				default: () => slots.default ? slots.default(slotProps.value) : createVNode(Fragment, null, [
					slots.colgroup?.(slotProps.value),
					!props.hideDefaultHeader && createVNode("thead", {
						"key": "thead",
						"class": "v-data-table__thead",
						"role": "rowgroup"
					}, [createVNode(VDataTableHeaders, dataTableHeadersProps, slots)]),
					slots.thead?.(slotProps.value),
					!props.hideDefaultBody && createVNode("tbody", {
						"class": "v-data-table__tbody",
						"role": "rowgroup"
					}, [
						slots["body.prepend"]?.(slotProps.value),
						slots.body ? slots.body(slotProps.value) : createVNode(VDataTableRows, mergeProps(attrs, dataTableRowsProps, { "items": flatItems.value }), slots),
						slots["body.append"]?.(slotProps.value)
					]),
					slots.tbody?.(slotProps.value),
					slots.tfoot?.(slotProps.value)
				]),
				bottom: () => slots.bottom ? slots.bottom(slotProps.value) : !props.hideDefaultFooter && createVNode(Fragment, null, [createVNode(VDivider, null, null), createVNode(VDataTableFooter, dataTableFooterProps, { prepend: slots["footer.prepend"] })])
			});
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VGrid/VContainer.js
var makeVContainerProps = propsFactory({
	fluid: {
		type: Boolean,
		default: false
	},
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeTagProps()
}, "VContainer");
var VContainer = genericComponent()({
	name: "VContainer",
	props: makeVContainerProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { rtlClasses } = useRtl();
		const { dimensionStyles } = useDimension(props);
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-container",
				{ "v-container--fluid": props.fluid },
				rtlClasses.value,
				props.class
			],
			"style": [dimensionStyles.value, props.style]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VGrid/VCol.js
var breakpointProps = breakpoints.reduce((props, val) => {
	props[val] = {
		type: [
			Boolean,
			String,
			Number
		],
		default: false
	};
	return props;
}, {});
var offsetProps = breakpoints.reduce((props, val) => {
	const offsetKey = "offset" + capitalize(val);
	props[offsetKey] = {
		type: [String, Number],
		default: null
	};
	return props;
}, {});
var orderProps = breakpoints.reduce((props, val) => {
	const orderKey = "order" + capitalize(val);
	props[orderKey] = {
		type: [String, Number],
		default: null
	};
	return props;
}, {});
var propMap$1 = {
	col: Object.keys(breakpointProps),
	offset: Object.keys(offsetProps),
	order: Object.keys(orderProps)
};
function breakpointClass$1(type, prop, val) {
	let className = type;
	if (val == null || val === false) return;
	if (prop) {
		const breakpoint = prop.replace(type, "");
		className += `-${breakpoint}`;
	}
	if (type === "col") className = "v-" + className;
	if (type === "col" && (val === "" || val === true)) return className.toLowerCase();
	className += `-${val}`;
	return className.toLowerCase();
}
var ALIGN_SELF_VALUES = [
	"auto",
	"start",
	"end",
	"center",
	"baseline",
	"stretch"
];
var makeVColProps = propsFactory({
	cols: {
		type: [
			Boolean,
			String,
			Number
		],
		default: false
	},
	...breakpointProps,
	offset: {
		type: [String, Number],
		default: null
	},
	...offsetProps,
	order: {
		type: [String, Number],
		default: null
	},
	...orderProps,
	alignSelf: {
		type: String,
		default: null,
		validator: (str) => ALIGN_SELF_VALUES.includes(str)
	},
	...makeComponentProps(),
	...makeTagProps()
}, "VCol");
var VCol = genericComponent()({
	name: "VCol",
	props: makeVColProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const classes = computed(() => {
			const classList = [];
			let type;
			for (type in propMap$1) propMap$1[type].forEach((prop) => {
				const value = props[prop];
				const className = breakpointClass$1(type, prop, value);
				if (className) classList.push(className);
			});
			const hasColClasses = classList.some((className) => className.startsWith("v-col-"));
			classList.push({
				"v-col": !hasColClasses || !props.cols,
				[`v-col-${props.cols}`]: props.cols,
				[`offset-${props.offset}`]: props.offset,
				[`order-${props.order}`]: props.order,
				[`align-self-${props.alignSelf}`]: props.alignSelf
			});
			return classList;
		});
		return () => h(props.tag, {
			class: [classes.value, props.class],
			style: props.style
		}, slots.default?.());
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VGrid/VRow.js
var ALIGNMENT = [
	"start",
	"end",
	"center"
];
var SPACE = [
	"space-between",
	"space-around",
	"space-evenly"
];
function makeRowProps(prefix, def) {
	return breakpoints.reduce((props, val) => {
		const prefixKey = prefix + capitalize(val);
		props[prefixKey] = def();
		return props;
	}, {});
}
var ALIGN_VALUES = [
	...ALIGNMENT,
	"baseline",
	"stretch"
];
var alignValidator = (str) => ALIGN_VALUES.includes(str);
var alignProps = makeRowProps("align", () => ({
	type: String,
	default: null,
	validator: alignValidator
}));
var JUSTIFY_VALUES = [...ALIGNMENT, ...SPACE];
var justifyValidator = (str) => JUSTIFY_VALUES.includes(str);
var justifyProps = makeRowProps("justify", () => ({
	type: String,
	default: null,
	validator: justifyValidator
}));
var ALIGN_CONTENT_VALUES = [
	...ALIGNMENT,
	...SPACE,
	"stretch"
];
var alignContentValidator = (str) => ALIGN_CONTENT_VALUES.includes(str);
var alignContentProps = makeRowProps("alignContent", () => ({
	type: String,
	default: null,
	validator: alignContentValidator
}));
var propMap = {
	align: Object.keys(alignProps),
	justify: Object.keys(justifyProps),
	alignContent: Object.keys(alignContentProps)
};
var classMap = {
	align: "align",
	justify: "justify",
	alignContent: "align-content"
};
function breakpointClass(type, prop, val) {
	let className = classMap[type];
	if (val == null) return;
	if (prop) {
		const breakpoint = prop.replace(type, "");
		className += `-${breakpoint}`;
	}
	className += `-${val}`;
	return className.toLowerCase();
}
var makeVRowProps = propsFactory({
	dense: Boolean,
	noGutters: Boolean,
	align: {
		type: String,
		default: null,
		validator: alignValidator
	},
	...alignProps,
	justify: {
		type: String,
		default: null,
		validator: justifyValidator
	},
	...justifyProps,
	alignContent: {
		type: String,
		default: null,
		validator: alignContentValidator
	},
	...alignContentProps,
	...makeComponentProps(),
	...makeTagProps()
}, "VRow");
var VRow = genericComponent()({
	name: "VRow",
	props: makeVRowProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const classes = computed(() => {
			const classList = [];
			let type;
			for (type in propMap) propMap[type].forEach((prop) => {
				const value = props[prop];
				const className = breakpointClass(type, prop, value);
				if (className) classList.push(className);
			});
			classList.push({
				"v-row--no-gutters": props.noGutters,
				"v-row--dense": props.dense,
				[`align-${props.align}`]: props.align,
				[`justify-${props.justify}`]: props.justify,
				[`align-content-${props.alignContent}`]: props.alignContent
			});
			return classList;
		});
		return () => h(props.tag, {
			class: [
				"v-row",
				classes.value,
				props.class
			],
			style: props.style
		}, slots.default?.());
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VGrid/VSpacer.js
var VSpacer = createSimpleFunctional("v-spacer", "div", "VSpacer");
//#endregion
//#region node_modules/vuetify/lib/components/VDatePicker/VDatePickerControls.js
var makeVDatePickerControlsProps = propsFactory({
	active: {
		type: [String, Array],
		default: void 0
	},
	controlHeight: [Number, String],
	disabled: {
		type: [
			Boolean,
			String,
			Array
		],
		default: null
	},
	nextIcon: {
		type: IconValue,
		default: "$next"
	},
	prevIcon: {
		type: IconValue,
		default: "$prev"
	},
	modeIcon: {
		type: IconValue,
		default: "$subgroup"
	},
	text: String,
	viewMode: {
		type: String,
		default: "month"
	}
}, "VDatePickerControls");
var VDatePickerControls = genericComponent()({
	name: "VDatePickerControls",
	props: makeVDatePickerControlsProps(),
	emits: {
		"click:year": () => true,
		"click:month": () => true,
		"click:prev": () => true,
		"click:next": () => true,
		"click:text": () => true
	},
	setup(props, _ref) {
		let { emit } = _ref;
		const disableMonth = computed(() => {
			return Array.isArray(props.disabled) ? props.disabled.includes("text") : !!props.disabled;
		});
		const disableYear = computed(() => {
			return Array.isArray(props.disabled) ? props.disabled.includes("mode") : !!props.disabled;
		});
		const disablePrev = computed(() => {
			return Array.isArray(props.disabled) ? props.disabled.includes("prev") : !!props.disabled;
		});
		const disableNext = computed(() => {
			return Array.isArray(props.disabled) ? props.disabled.includes("next") : !!props.disabled;
		});
		function onClickPrev() {
			emit("click:prev");
		}
		function onClickNext() {
			emit("click:next");
		}
		function onClickYear() {
			emit("click:year");
		}
		function onClickMonth() {
			emit("click:month");
		}
		useRender(() => {
			return createVNode("div", {
				"class": ["v-date-picker-controls"],
				"style": { "--v-date-picker-controls-height": convertToUnit(props.controlHeight) }
			}, [
				createVNode(VBtn, {
					"class": "v-date-picker-controls__month-btn",
					"data-testid": "month-btn",
					"disabled": disableMonth.value,
					"text": props.text,
					"variant": "text",
					"rounded": true,
					"onClick": onClickMonth
				}, null),
				createVNode(VBtn, {
					"class": "v-date-picker-controls__mode-btn",
					"data-testid": "year-btn",
					"disabled": disableYear.value,
					"density": "comfortable",
					"icon": props.modeIcon,
					"variant": "text",
					"onClick": onClickYear
				}, null),
				createVNode(VSpacer, null, null),
				createVNode("div", { "class": "v-date-picker-controls__month" }, [createVNode(VBtn, {
					"data-testid": "prev-month",
					"disabled": disablePrev.value,
					"density": "comfortable",
					"icon": props.prevIcon,
					"variant": "text",
					"onClick": onClickPrev
				}, null), createVNode(VBtn, {
					"data-testid": "next-month",
					"disabled": disableNext.value,
					"icon": props.nextIcon,
					"density": "comfortable",
					"variant": "text",
					"onClick": onClickNext
				}, null)])
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDatePicker/VDatePickerHeader.js
var makeVDatePickerHeaderProps = propsFactory({
	appendIcon: IconValue,
	color: String,
	header: String,
	transition: String,
	onClick: EventProp()
}, "VDatePickerHeader");
var VDatePickerHeader = genericComponent()({
	name: "VDatePickerHeader",
	props: makeVDatePickerHeaderProps(),
	emits: {
		click: () => true,
		"click:append": () => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		function onClick() {
			emit("click");
		}
		function onClickAppend() {
			emit("click:append");
		}
		useRender(() => {
			const hasContent = !!(slots.default || props.header);
			const hasAppend = !!(slots.append || props.appendIcon);
			return createVNode("div", {
				"class": [
					"v-date-picker-header",
					{ "v-date-picker-header--clickable": !!props.onClick },
					backgroundColorClasses.value
				],
				"style": backgroundColorStyles.value,
				"onClick": onClick
			}, [
				slots.prepend && createVNode("div", {
					"key": "prepend",
					"class": "v-date-picker-header__prepend"
				}, [slots.prepend()]),
				hasContent && createVNode(MaybeTransition, {
					"key": "content",
					"name": props.transition
				}, { default: () => [createVNode("div", {
					"key": props.header,
					"class": "v-date-picker-header__content"
				}, [slots.default?.() ?? props.header])] }),
				hasAppend && createVNode("div", { "class": "v-date-picker-header__append" }, [!slots.append ? createVNode(VBtn, {
					"key": "append-btn",
					"icon": props.appendIcon,
					"variant": "text",
					"onClick": onClickAppend
				}, null) : createVNode(VDefaultsProvider, {
					"key": "append-defaults",
					"disabled": !props.appendIcon,
					"defaults": { VBtn: {
						icon: props.appendIcon,
						variant: "text"
					} }
				}, { default: () => [slots.append?.()] })])
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/composables/calendar.js
var makeCalendarProps = propsFactory({
	allowedDates: [Array, Function],
	disabled: {
		type: Boolean,
		default: null
	},
	displayValue: null,
	modelValue: Array,
	month: [Number, String],
	max: null,
	min: null,
	showAdjacentMonths: Boolean,
	year: [Number, String],
	weekdays: {
		type: Array,
		default: () => [
			0,
			1,
			2,
			3,
			4,
			5,
			6
		]
	},
	weeksInMonth: {
		type: String,
		default: "dynamic"
	},
	firstDayOfWeek: {
		type: [Number, String],
		default: void 0
	}
}, "calendar");
function useCalendar(props) {
	const adapter = useDate();
	const model = useProxiedModel(props, "modelValue", [], (v) => wrapInArray(v).map((i) => adapter.date(i)));
	const displayValue = computed(() => {
		if (props.displayValue) return adapter.date(props.displayValue);
		if (model.value.length > 0) return adapter.date(model.value[0]);
		if (props.min) return adapter.date(props.min);
		if (Array.isArray(props.allowedDates)) return adapter.date(props.allowedDates[0]);
		return adapter.date();
	});
	const year = useProxiedModel(props, "year", void 0, (v) => {
		const value = v != null ? Number(v) : adapter.getYear(displayValue.value);
		return adapter.startOfYear(adapter.setYear(adapter.date(), value));
	}, (v) => adapter.getYear(v));
	const month = useProxiedModel(props, "month", void 0, (v) => {
		const value = v != null ? Number(v) : adapter.getMonth(displayValue.value);
		const date = adapter.setYear(adapter.startOfMonth(adapter.date()), adapter.getYear(year.value));
		return adapter.setMonth(date, value);
	}, (v) => adapter.getMonth(v));
	const weekDays = computed(() => {
		const firstDayOfWeek = adapter.toJsDate(adapter.startOfWeek(adapter.date(), props.firstDayOfWeek)).getDay();
		return [
			0,
			1,
			2,
			3,
			4,
			5,
			6
		].map((day) => (day + firstDayOfWeek) % 7);
	});
	const weeksInMonth = computed(() => {
		const weeks = adapter.getWeekArray(month.value, props.firstDayOfWeek);
		const days = weeks.flat();
		const daysInMonth = 42;
		if (props.weeksInMonth === "static" && days.length < daysInMonth) {
			const lastDay = days[days.length - 1];
			let week = [];
			for (let day = 1; day <= daysInMonth - days.length; day++) {
				week.push(adapter.addDays(lastDay, day));
				if (day % 7 === 0) {
					weeks.push(week);
					week = [];
				}
			}
		}
		return weeks;
	});
	function genDays(days, today) {
		return days.filter((date) => {
			return weekDays.value.includes(adapter.toJsDate(date).getDay());
		}).map((date, index) => {
			const isoDate = adapter.toISO(date);
			const isAdjacent = !adapter.isSameMonth(date, month.value);
			const isStart = adapter.isSameDay(date, adapter.startOfMonth(month.value));
			const isEnd = adapter.isSameDay(date, adapter.endOfMonth(month.value));
			const isSame = adapter.isSameDay(date, month.value);
			return {
				date,
				formatted: adapter.format(date, "keyboardDate"),
				isAdjacent,
				isDisabled: isDisabled(date),
				isEnd,
				isHidden: isAdjacent && !props.showAdjacentMonths,
				isSame,
				isSelected: model.value.some((value) => adapter.isSameDay(date, value)),
				isStart,
				isToday: adapter.isSameDay(date, today),
				isWeekEnd: index % 7 === 6,
				isWeekStart: index % 7 === 0,
				isoDate,
				localized: adapter.format(date, "dayOfMonth"),
				month: adapter.getMonth(date),
				year: adapter.getYear(date)
			};
		});
	}
	const daysInWeek = computed(() => {
		const lastDay = adapter.startOfWeek(displayValue.value, props.firstDayOfWeek);
		const week = [];
		for (let day = 0; day <= 6; day++) week.push(adapter.addDays(lastDay, day));
		return genDays(week, adapter.date());
	});
	const daysInMonth = computed(() => {
		return genDays(weeksInMonth.value.flat(), adapter.date());
	});
	const weekNumbers = computed(() => {
		return weeksInMonth.value.map((week) => {
			return week.length ? adapter.getWeek(week[0], props.firstDayOfWeek) : null;
		});
	});
	function isDisabled(value) {
		if (props.disabled) return true;
		const date = adapter.date(value);
		if (props.min && adapter.isAfter(adapter.date(props.min), date)) return true;
		if (props.max && adapter.isAfter(date, adapter.date(props.max))) return true;
		if (Array.isArray(props.allowedDates) && props.allowedDates.length > 0) return !props.allowedDates.some((d) => adapter.isSameDay(adapter.date(d), date));
		if (typeof props.allowedDates === "function") return !props.allowedDates(date);
		return !props.weekdays.includes(adapter.toJsDate(date).getDay());
	}
	return {
		displayValue,
		daysInMonth,
		daysInWeek,
		genDays,
		model,
		weeksInMonth,
		weekDays,
		weekNumbers
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VDatePicker/VDatePickerMonth.js
var makeVDatePickerMonthProps = propsFactory({
	color: String,
	hideWeekdays: Boolean,
	multiple: [
		Boolean,
		Number,
		String
	],
	showWeek: Boolean,
	transition: {
		type: String,
		default: "picker-transition"
	},
	reverseTransition: {
		type: String,
		default: "picker-reverse-transition"
	},
	...omit(makeCalendarProps(), ["displayValue"])
}, "VDatePickerMonth");
var VDatePickerMonth = genericComponent()({
	name: "VDatePickerMonth",
	props: makeVDatePickerMonthProps(),
	emits: {
		"update:modelValue": (date) => true,
		"update:month": (date) => true,
		"update:year": (date) => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const daysRef = ref();
		const { daysInMonth, model, weekNumbers } = useCalendar(props);
		const adapter = useDate();
		const rangeStart = shallowRef();
		const rangeStop = shallowRef();
		const isReverse = shallowRef(false);
		const transition = toRef(() => {
			return !isReverse.value ? props.transition : props.reverseTransition;
		});
		if (props.multiple === "range" && model.value.length > 0) {
			rangeStart.value = model.value[0];
			if (model.value.length > 1) rangeStop.value = model.value[model.value.length - 1];
		}
		const atMax = computed(() => {
			const max = ["number", "string"].includes(typeof props.multiple) ? Number(props.multiple) : Infinity;
			return model.value.length >= max;
		});
		watch(daysInMonth, (val, oldVal) => {
			if (!oldVal) return;
			isReverse.value = adapter.isBefore(val[0].date, oldVal[0].date);
		});
		function onRangeClick(value) {
			const _value = adapter.startOfDay(value);
			if (model.value.length === 0) rangeStart.value = void 0;
			else if (model.value.length === 1) {
				rangeStart.value = model.value[0];
				rangeStop.value = void 0;
			}
			if (!rangeStart.value) {
				rangeStart.value = _value;
				model.value = [rangeStart.value];
			} else if (!rangeStop.value) {
				if (adapter.isSameDay(_value, rangeStart.value)) {
					rangeStart.value = void 0;
					model.value = [];
					return;
				} else if (adapter.isBefore(_value, rangeStart.value)) {
					rangeStop.value = adapter.endOfDay(rangeStart.value);
					rangeStart.value = _value;
				} else rangeStop.value = adapter.endOfDay(_value);
				const diff = adapter.getDiff(rangeStop.value, rangeStart.value, "days");
				const datesInRange = [rangeStart.value];
				for (let i = 1; i < diff; i++) {
					const nextDate = adapter.addDays(rangeStart.value, i);
					datesInRange.push(nextDate);
				}
				datesInRange.push(rangeStop.value);
				model.value = datesInRange;
			} else {
				rangeStart.value = value;
				rangeStop.value = void 0;
				model.value = [rangeStart.value];
			}
		}
		function onMultipleClick(value) {
			const index = model.value.findIndex((selection) => adapter.isSameDay(selection, value));
			if (index === -1) model.value = [...model.value, value];
			else {
				const value = [...model.value];
				value.splice(index, 1);
				model.value = value;
			}
		}
		function onClick(value) {
			if (props.multiple === "range") onRangeClick(value);
			else if (props.multiple) onMultipleClick(value);
			else model.value = [value];
		}
		useRender(() => createVNode("div", { "class": "v-date-picker-month" }, [props.showWeek && createVNode("div", {
			"key": "weeks",
			"class": "v-date-picker-month__weeks"
		}, [!props.hideWeekdays && createVNode("div", {
			"key": "hide-week-days",
			"class": "v-date-picker-month__day"
		}, [createTextVNode("\xA0")]), weekNumbers.value.map((week) => createVNode("div", { "class": ["v-date-picker-month__day", "v-date-picker-month__day--adjacent"] }, [week]))]), createVNode(MaybeTransition, { "name": transition.value }, { default: () => [createVNode("div", {
			"ref": daysRef,
			"key": daysInMonth.value[0].date?.toString(),
			"class": "v-date-picker-month__days"
		}, [!props.hideWeekdays && adapter.getWeekdays(props.firstDayOfWeek).map((weekDay) => createVNode("div", { "class": ["v-date-picker-month__day", "v-date-picker-month__weekday"] }, [weekDay])), daysInMonth.value.map((item, i) => {
			const slotProps = {
				props: {
					class: "v-date-picker-month__day-btn",
					color: item.isSelected || item.isToday ? props.color : void 0,
					disabled: item.isDisabled,
					icon: true,
					ripple: false,
					text: item.localized,
					variant: item.isSelected ? "flat" : item.isToday ? "outlined" : "text",
					onClick: () => onClick(item.date)
				},
				item,
				i
			};
			if (atMax.value && !item.isSelected) item.isDisabled = true;
			return createVNode("div", {
				"class": ["v-date-picker-month__day", {
					"v-date-picker-month__day--adjacent": item.isAdjacent,
					"v-date-picker-month__day--hide-adjacent": item.isHidden,
					"v-date-picker-month__day--selected": item.isSelected,
					"v-date-picker-month__day--week-end": item.isWeekEnd,
					"v-date-picker-month__day--week-start": item.isWeekStart
				}],
				"data-v-date": !item.isDisabled ? item.isoDate : void 0
			}, [(props.showAdjacentMonths || !item.isAdjacent) && (slots.day?.(slotProps) ?? createVNode(VBtn, slotProps.props, null))]);
		})])] })]));
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDatePicker/VDatePickerMonths.js
var makeVDatePickerMonthsProps = propsFactory({
	color: String,
	height: [String, Number],
	min: null,
	max: null,
	modelValue: Number,
	year: Number
}, "VDatePickerMonths");
var VDatePickerMonths = genericComponent()({
	name: "VDatePickerMonths",
	props: makeVDatePickerMonthsProps(),
	emits: { "update:modelValue": (date) => true },
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const adapter = useDate();
		const model = useProxiedModel(props, "modelValue");
		const months = computed(() => {
			let date = adapter.startOfYear(adapter.date());
			if (props.year) date = adapter.setYear(date, props.year);
			return createRange(12).map((i) => {
				const text = adapter.format(date, "monthShort");
				const isDisabled = !!(props.min && adapter.isAfter(adapter.startOfMonth(adapter.date(props.min)), date) || props.max && adapter.isAfter(date, adapter.startOfMonth(adapter.date(props.max))));
				date = adapter.getNextMonth(date);
				return {
					isDisabled,
					text,
					value: i
				};
			});
		});
		watchEffect(() => {
			model.value = model.value ?? adapter.getMonth(adapter.date());
		});
		useRender(() => createVNode("div", {
			"class": "v-date-picker-months",
			"style": { height: convertToUnit(props.height) }
		}, [createVNode("div", { "class": "v-date-picker-months__content" }, [months.value.map((month, i) => {
			const btnProps = {
				active: model.value === i,
				color: model.value === i ? props.color : void 0,
				disabled: month.isDisabled,
				rounded: true,
				text: month.text,
				variant: model.value === month.value ? "flat" : "text",
				onClick: () => onClick(i)
			};
			function onClick(i) {
				if (model.value === i) {
					emit("update:modelValue", model.value);
					return;
				}
				model.value = i;
			}
			return slots.month?.({
				month,
				i,
				props: btnProps
			}) ?? createVNode(VBtn, mergeProps({ "key": "month" }, btnProps), null);
		})])]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDatePicker/VDatePickerYears.js
var makeVDatePickerYearsProps = propsFactory({
	color: String,
	height: [String, Number],
	min: null,
	max: null,
	modelValue: Number
}, "VDatePickerYears");
var VDatePickerYears = genericComponent()({
	name: "VDatePickerYears",
	props: makeVDatePickerYearsProps(),
	emits: { "update:modelValue": (year) => true },
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const adapter = useDate();
		const model = useProxiedModel(props, "modelValue");
		const years = computed(() => {
			const year = adapter.getYear(adapter.date());
			let min = year - 100;
			let max = year + 52;
			if (props.min) min = adapter.getYear(adapter.date(props.min));
			if (props.max) max = adapter.getYear(adapter.date(props.max));
			let date = adapter.startOfYear(adapter.date());
			date = adapter.setYear(date, min);
			return createRange(max - min + 1, min).map((i) => {
				const text = adapter.format(date, "year");
				date = adapter.setYear(date, adapter.getYear(date) + 1);
				return {
					text,
					value: i
				};
			});
		});
		watchEffect(() => {
			model.value = model.value ?? adapter.getYear(adapter.date());
		});
		const yearRef = templateRef();
		onMounted(async () => {
			await nextTick();
			yearRef.el?.scrollIntoView({ block: "center" });
		});
		useRender(() => createVNode("div", {
			"class": "v-date-picker-years",
			"style": { height: convertToUnit(props.height) }
		}, [createVNode("div", { "class": "v-date-picker-years__content" }, [years.value.map((year, i) => {
			const btnProps = {
				ref: model.value === year.value ? yearRef : void 0,
				active: model.value === year.value,
				color: model.value === year.value ? props.color : void 0,
				rounded: true,
				text: year.text,
				variant: model.value === year.value ? "flat" : "text",
				onClick: () => {
					if (model.value === year.value) {
						emit("update:modelValue", model.value);
						return;
					}
					model.value = year.value;
				}
			};
			return slots.year?.({
				year,
				i,
				props: btnProps
			}) ?? createVNode(VBtn, mergeProps({ "key": "month" }, btnProps), null);
		})])]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VDatePicker/VDatePicker.js
var makeVDatePickerProps = propsFactory({
	header: {
		type: String,
		default: "$vuetify.datePicker.header"
	},
	headerColor: String,
	...makeVDatePickerControlsProps(),
	...makeVDatePickerMonthProps({ weeksInMonth: "static" }),
	...omit(makeVDatePickerMonthsProps(), ["modelValue"]),
	...omit(makeVDatePickerYearsProps(), ["modelValue"]),
	...makeVPickerProps({ title: "$vuetify.datePicker.title" }),
	modelValue: null
}, "VDatePicker");
var VDatePicker = genericComponent()({
	name: "VDatePicker",
	props: makeVDatePickerProps(),
	emits: {
		"update:modelValue": (date) => true,
		"update:month": (date) => true,
		"update:year": (date) => true,
		"update:viewMode": (date) => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const adapter = useDate();
		const { t } = useLocale();
		const { rtlClasses } = useRtl();
		const model = useProxiedModel(props, "modelValue", void 0, (v) => wrapInArray(v).map((i) => adapter.date(i)), (v) => props.multiple ? v : v[0]);
		const viewMode = useProxiedModel(props, "viewMode");
		const minDate = computed(() => {
			const date = adapter.date(props.min);
			return props.min && adapter.isValid(date) ? date : null;
		});
		const maxDate = computed(() => {
			const date = adapter.date(props.max);
			return props.max && adapter.isValid(date) ? date : null;
		});
		const internal = computed(() => {
			const today = adapter.date();
			let value = today;
			if (model.value?.[0]) value = adapter.date(model.value[0]);
			else if (minDate.value && adapter.isBefore(today, minDate.value)) value = minDate.value;
			else if (maxDate.value && adapter.isAfter(today, maxDate.value)) value = maxDate.value;
			return value && adapter.isValid(value) ? value : today;
		});
		const headerColor = toRef(() => props.headerColor ?? props.color);
		const month = ref(Number(props.month ?? adapter.getMonth(adapter.startOfMonth(internal.value))));
		const year = ref(Number(props.year ?? adapter.getYear(adapter.startOfYear(adapter.setMonth(internal.value, month.value)))));
		const isReversing = shallowRef(false);
		const header = computed(() => {
			if (props.multiple && model.value.length > 1) return t("$vuetify.datePicker.itemsSelected", model.value.length);
			return model.value[0] && adapter.isValid(model.value[0]) ? adapter.format(adapter.date(model.value[0]), "normalDateWithWeekday") : t(props.header);
		});
		const text = computed(() => {
			let date = adapter.date();
			date = adapter.setDate(date, 1);
			date = adapter.setMonth(date, month.value);
			date = adapter.setYear(date, year.value);
			return adapter.format(date, "monthAndYear");
		});
		const headerTransition = toRef(() => `date-picker-header${isReversing.value ? "-reverse" : ""}-transition`);
		const disabled = computed(() => {
			if (props.disabled) return true;
			const targets = [];
			if (viewMode.value !== "month") targets.push(...["prev", "next"]);
			else {
				let _date = adapter.date();
				_date = adapter.startOfMonth(_date);
				_date = adapter.setMonth(_date, month.value);
				_date = adapter.setYear(_date, year.value);
				if (minDate.value) {
					const date = adapter.addDays(adapter.startOfMonth(_date), -1);
					adapter.isAfter(minDate.value, date) && targets.push("prev");
				}
				if (maxDate.value) {
					const date = adapter.addDays(adapter.endOfMonth(_date), 1);
					adapter.isAfter(date, maxDate.value) && targets.push("next");
				}
			}
			return targets;
		});
		function onClickNext() {
			if (month.value < 11) month.value++;
			else {
				year.value++;
				month.value = 0;
				onUpdateYear(year.value);
			}
			onUpdateMonth(month.value);
		}
		function onClickPrev() {
			if (month.value > 0) month.value--;
			else {
				year.value--;
				month.value = 11;
				onUpdateYear(year.value);
			}
			onUpdateMonth(month.value);
		}
		function onClickDate() {
			viewMode.value = "month";
		}
		function onClickMonth() {
			viewMode.value = viewMode.value === "months" ? "month" : "months";
		}
		function onClickYear() {
			viewMode.value = viewMode.value === "year" ? "month" : "year";
		}
		function onUpdateMonth(value) {
			if (viewMode.value === "months") onClickMonth();
			emit("update:month", value);
		}
		function onUpdateYear(value) {
			if (viewMode.value === "year") onClickYear();
			emit("update:year", value);
		}
		watch(model, (val, oldVal) => {
			const arrBefore = wrapInArray(oldVal);
			const arrAfter = wrapInArray(val);
			if (!arrAfter.length) return;
			const before = adapter.date(arrBefore[arrBefore.length - 1]);
			const after = adapter.date(arrAfter[arrAfter.length - 1]);
			const newMonth = adapter.getMonth(after);
			const newYear = adapter.getYear(after);
			if (newMonth !== month.value) {
				month.value = newMonth;
				onUpdateMonth(month.value);
			}
			if (newYear !== year.value) {
				year.value = newYear;
				onUpdateYear(year.value);
			}
			isReversing.value = adapter.isBefore(before, after);
		});
		useRender(() => {
			const pickerProps = VPicker.filterProps(props);
			const datePickerControlsProps = VDatePickerControls.filterProps(props);
			const datePickerHeaderProps = VDatePickerHeader.filterProps(props);
			const datePickerMonthProps = VDatePickerMonth.filterProps(props);
			const datePickerMonthsProps = omit(VDatePickerMonths.filterProps(props), ["modelValue"]);
			const datePickerYearsProps = omit(VDatePickerYears.filterProps(props), ["modelValue"]);
			const headerProps = {
				color: headerColor.value,
				header: header.value,
				transition: headerTransition.value
			};
			return createVNode(VPicker, mergeProps(pickerProps, {
				"color": headerColor.value,
				"class": [
					"v-date-picker",
					`v-date-picker--${viewMode.value}`,
					{ "v-date-picker--show-week": props.showWeek },
					rtlClasses.value,
					props.class
				],
				"style": props.style
			}), {
				title: () => slots.title?.() ?? createVNode("div", { "class": "v-date-picker__title" }, [t(props.title)]),
				header: () => slots.header ? createVNode(VDefaultsProvider, { "defaults": { VDatePickerHeader: { ...headerProps } } }, { default: () => [slots.header?.(headerProps)] }) : createVNode(VDatePickerHeader, mergeProps({ "key": "header" }, datePickerHeaderProps, headerProps, { "onClick": viewMode.value !== "month" ? onClickDate : void 0 }), {
					...slots,
					default: void 0
				}),
				default: () => createVNode(Fragment, null, [createVNode(VDatePickerControls, mergeProps(datePickerControlsProps, {
					"disabled": disabled.value,
					"text": text.value,
					"onClick:next": onClickNext,
					"onClick:prev": onClickPrev,
					"onClick:month": onClickMonth,
					"onClick:year": onClickYear
				}), null), createVNode(VFadeTransition, { "hideOnLeave": true }, { default: () => [viewMode.value === "months" ? createVNode(VDatePickerMonths, mergeProps({ "key": "date-picker-months" }, datePickerMonthsProps, {
					"modelValue": month.value,
					"onUpdate:modelValue": [($event) => month.value = $event, onUpdateMonth],
					"min": minDate.value,
					"max": maxDate.value,
					"year": year.value
				}), null) : viewMode.value === "year" ? createVNode(VDatePickerYears, mergeProps({ "key": "date-picker-years" }, datePickerYearsProps, {
					"modelValue": year.value,
					"onUpdate:modelValue": [($event) => year.value = $event, onUpdateYear],
					"min": minDate.value,
					"max": maxDate.value
				}), null) : createVNode(VDatePickerMonth, mergeProps({ "key": "date-picker-month" }, datePickerMonthProps, {
					"modelValue": model.value,
					"onUpdate:modelValue": ($event) => model.value = $event,
					"month": month.value,
					"onUpdate:month": [($event) => month.value = $event, onUpdateMonth],
					"year": year.value,
					"onUpdate:year": [($event) => year.value = $event, onUpdateYear],
					"min": minDate.value,
					"max": maxDate.value
				}), null)] })]),
				actions: slots.actions
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VEmptyState/VEmptyState.js
var makeVEmptyStateProps = propsFactory({
	actionText: String,
	bgColor: String,
	color: String,
	icon: IconValue,
	image: String,
	justify: {
		type: String,
		default: "center"
	},
	headline: String,
	title: String,
	text: String,
	textWidth: {
		type: [Number, String],
		default: 500
	},
	href: String,
	to: String,
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeSizeProps({ size: void 0 }),
	...makeThemeProps()
}, "VEmptyState");
var VEmptyState = genericComponent()({
	name: "VEmptyState",
	props: makeVEmptyStateProps(),
	emits: { "click:action": (e) => true },
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { dimensionStyles } = useDimension(props);
		const { displayClasses } = useDisplay();
		function onClickAction(e) {
			emit("click:action", e);
		}
		useRender(() => {
			const hasActions = !!(slots.actions || props.actionText);
			const hasHeadline = !!(slots.headline || props.headline);
			const hasTitle = !!(slots.title || props.title);
			const hasText = !!(slots.text || props.text);
			const hasMedia = !!(slots.media || props.image || props.icon);
			const size = props.size || (props.image ? 200 : 96);
			return createVNode("div", {
				"class": [
					"v-empty-state",
					{ [`v-empty-state--${props.justify}`]: true },
					themeClasses.value,
					backgroundColorClasses.value,
					displayClasses.value,
					props.class
				],
				"style": [
					backgroundColorStyles.value,
					dimensionStyles.value,
					props.style
				]
			}, [
				hasMedia && createVNode("div", {
					"key": "media",
					"class": "v-empty-state__media"
				}, [!slots.media ? createVNode(Fragment, null, [props.image ? createVNode(VImg, {
					"key": "image",
					"src": props.image,
					"height": size
				}, null) : props.icon ? createVNode(VIcon, {
					"key": "icon",
					"color": props.color,
					"size": size,
					"icon": props.icon
				}, null) : void 0]) : createVNode(VDefaultsProvider, {
					"key": "media-defaults",
					"defaults": {
						VImg: {
							src: props.image,
							height: size
						},
						VIcon: {
							size,
							icon: props.icon
						}
					}
				}, { default: () => [slots.media()] })]),
				hasHeadline && createVNode("div", {
					"key": "headline",
					"class": "v-empty-state__headline"
				}, [slots.headline?.() ?? props.headline]),
				hasTitle && createVNode("div", {
					"key": "title",
					"class": "v-empty-state__title"
				}, [slots.title?.() ?? props.title]),
				hasText && createVNode("div", {
					"key": "text",
					"class": "v-empty-state__text",
					"style": { maxWidth: convertToUnit(props.textWidth) }
				}, [slots.text?.() ?? props.text]),
				slots.default && createVNode("div", {
					"key": "content",
					"class": "v-empty-state__content"
				}, [slots.default()]),
				hasActions && createVNode("div", {
					"key": "actions",
					"class": "v-empty-state__actions"
				}, [createVNode(VDefaultsProvider, { "defaults": { VBtn: {
					class: "v-empty-state__action-btn",
					color: props.color ?? "surface-variant",
					href: props.href,
					text: props.actionText,
					to: props.to
				} } }, { default: () => [slots.actions?.({ props: { onClick: onClickAction } }) ?? createVNode(VBtn, { "onClick": onClickAction }, null)] })])
			]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VExpansionPanel/shared.js
var VExpansionPanelSymbol = Symbol.for("vuetify:v-expansion-panel");
//#endregion
//#region node_modules/vuetify/lib/components/VExpansionPanel/VExpansionPanelText.js
var makeVExpansionPanelTextProps = propsFactory({
	...makeComponentProps(),
	...makeLazyProps()
}, "VExpansionPanelText");
var VExpansionPanelText = genericComponent()({
	name: "VExpansionPanelText",
	props: makeVExpansionPanelTextProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const expansionPanel = inject(VExpansionPanelSymbol);
		if (!expansionPanel) throw new Error("[Vuetify] v-expansion-panel-text needs to be placed inside v-expansion-panel");
		const { hasContent, onAfterLeave } = useLazy(props, expansionPanel.isSelected);
		useRender(() => createVNode(VExpandTransition, { "onAfterLeave": onAfterLeave }, { default: () => [withDirectives(createVNode("div", {
			"class": ["v-expansion-panel-text", props.class],
			"style": props.style
		}, [slots.default && hasContent.value && createVNode("div", { "class": "v-expansion-panel-text__wrapper" }, [slots.default?.()])]), [[vShow, expansionPanel.isSelected.value]])] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VExpansionPanel/VExpansionPanelTitle.js
var makeVExpansionPanelTitleProps = propsFactory({
	color: String,
	expandIcon: {
		type: IconValue,
		default: "$expand"
	},
	collapseIcon: {
		type: IconValue,
		default: "$collapse"
	},
	hideActions: Boolean,
	focusable: Boolean,
	static: Boolean,
	ripple: {
		type: [Boolean, Object],
		default: false
	},
	readonly: Boolean,
	...makeComponentProps(),
	...makeDimensionProps()
}, "VExpansionPanelTitle");
var VExpansionPanelTitle = genericComponent()({
	name: "VExpansionPanelTitle",
	directives: { Ripple },
	props: makeVExpansionPanelTitleProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const expansionPanel = inject(VExpansionPanelSymbol);
		if (!expansionPanel) throw new Error("[Vuetify] v-expansion-panel-title needs to be placed inside v-expansion-panel");
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { dimensionStyles } = useDimension(props);
		const slotProps = computed(() => ({
			collapseIcon: props.collapseIcon,
			disabled: expansionPanel.disabled.value,
			expanded: expansionPanel.isSelected.value,
			expandIcon: props.expandIcon,
			readonly: props.readonly
		}));
		const icon = toRef(() => expansionPanel.isSelected.value ? props.collapseIcon : props.expandIcon);
		useRender(() => withDirectives(createVNode("button", {
			"class": [
				"v-expansion-panel-title",
				{
					"v-expansion-panel-title--active": expansionPanel.isSelected.value,
					"v-expansion-panel-title--focusable": props.focusable,
					"v-expansion-panel-title--static": props.static
				},
				backgroundColorClasses.value,
				props.class
			],
			"style": [
				backgroundColorStyles.value,
				dimensionStyles.value,
				props.style
			],
			"type": "button",
			"tabindex": expansionPanel.disabled.value ? -1 : void 0,
			"disabled": expansionPanel.disabled.value,
			"aria-expanded": expansionPanel.isSelected.value,
			"onClick": !props.readonly ? expansionPanel.toggle : void 0
		}, [
			createVNode("span", { "class": "v-expansion-panel-title__overlay" }, null),
			slots.default?.(slotProps.value),
			!props.hideActions && createVNode(VDefaultsProvider, { "defaults": { VIcon: { icon: icon.value } } }, { default: () => [createVNode("span", { "class": "v-expansion-panel-title__icon" }, [slots.actions?.(slotProps.value) ?? createVNode(VIcon, null, null)])] })
		]), [[resolveDirective("ripple"), props.ripple]]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VExpansionPanel/VExpansionPanel.js
var makeVExpansionPanelProps = propsFactory({
	title: String,
	text: String,
	bgColor: String,
	...makeElevationProps(),
	...makeGroupItemProps(),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeVExpansionPanelTitleProps(),
	...makeVExpansionPanelTextProps()
}, "VExpansionPanel");
var VExpansionPanel = genericComponent()({
	name: "VExpansionPanel",
	props: makeVExpansionPanelProps(),
	emits: { "group:selected": (val) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const groupItem = useGroupItem(props, VExpansionPanelSymbol);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		const isDisabled = toRef(() => groupItem?.disabled.value || props.disabled);
		const selectedIndices = computed(() => groupItem.group.items.value.reduce((arr, item, index) => {
			if (groupItem.group.selected.value.includes(item.id)) arr.push(index);
			return arr;
		}, []));
		const isBeforeSelected = computed(() => {
			const index = groupItem.group.items.value.findIndex((item) => item.id === groupItem.id);
			return !groupItem.isSelected.value && selectedIndices.value.some((selectedIndex) => selectedIndex - index === 1);
		});
		const isAfterSelected = computed(() => {
			const index = groupItem.group.items.value.findIndex((item) => item.id === groupItem.id);
			return !groupItem.isSelected.value && selectedIndices.value.some((selectedIndex) => selectedIndex - index === -1);
		});
		provide(VExpansionPanelSymbol, groupItem);
		useRender(() => {
			const hasText = !!(slots.text || props.text);
			const hasTitle = !!(slots.title || props.title);
			const expansionPanelTitleProps = VExpansionPanelTitle.filterProps(props);
			const expansionPanelTextProps = VExpansionPanelText.filterProps(props);
			return createVNode(props.tag, {
				"class": [
					"v-expansion-panel",
					{
						"v-expansion-panel--active": groupItem.isSelected.value,
						"v-expansion-panel--before-active": isBeforeSelected.value,
						"v-expansion-panel--after-active": isAfterSelected.value,
						"v-expansion-panel--disabled": isDisabled.value
					},
					roundedClasses.value,
					backgroundColorClasses.value,
					props.class
				],
				"style": [backgroundColorStyles.value, props.style]
			}, { default: () => [createVNode("div", { "class": ["v-expansion-panel__shadow", ...elevationClasses.value] }, null), createVNode(VDefaultsProvider, { "defaults": {
				VExpansionPanelTitle: { ...expansionPanelTitleProps },
				VExpansionPanelText: { ...expansionPanelTextProps }
			} }, { default: () => [
				hasTitle && createVNode(VExpansionPanelTitle, { "key": "title" }, { default: () => [slots.title ? slots.title() : props.title] }),
				hasText && createVNode(VExpansionPanelText, { "key": "text" }, { default: () => [slots.text ? slots.text() : props.text] }),
				slots.default?.()
			] })] });
		});
		return { groupItem };
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VExpansionPanel/VExpansionPanels.js
var allowedVariants = [
	"default",
	"accordion",
	"inset",
	"popout"
];
var makeVExpansionPanelsProps = propsFactory({
	flat: Boolean,
	...makeGroupProps(),
	...pick(makeVExpansionPanelProps(), [
		"bgColor",
		"collapseIcon",
		"color",
		"eager",
		"elevation",
		"expandIcon",
		"focusable",
		"hideActions",
		"readonly",
		"ripple",
		"rounded",
		"tile",
		"static"
	]),
	...makeThemeProps(),
	...makeComponentProps(),
	...makeTagProps(),
	variant: {
		type: String,
		default: "default",
		validator: (v) => allowedVariants.includes(v)
	}
}, "VExpansionPanels");
var VExpansionPanels = genericComponent()({
	name: "VExpansionPanels",
	props: makeVExpansionPanelsProps(),
	emits: { "update:modelValue": (val) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { next, prev } = useGroup(props, VExpansionPanelSymbol);
		const { themeClasses } = provideTheme(props);
		const variantClass = toRef(() => props.variant && `v-expansion-panels--variant-${props.variant}`);
		provideDefaults({ VExpansionPanel: {
			bgColor: toRef(() => props.bgColor),
			collapseIcon: toRef(() => props.collapseIcon),
			color: toRef(() => props.color),
			eager: toRef(() => props.eager),
			elevation: toRef(() => props.elevation),
			expandIcon: toRef(() => props.expandIcon),
			focusable: toRef(() => props.focusable),
			hideActions: toRef(() => props.hideActions),
			readonly: toRef(() => props.readonly),
			ripple: toRef(() => props.ripple),
			rounded: toRef(() => props.rounded),
			static: toRef(() => props.static)
		} });
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-expansion-panels",
				{
					"v-expansion-panels--flat": props.flat,
					"v-expansion-panels--tile": props.tile
				},
				themeClasses.value,
				variantClass.value,
				props.class
			],
			"style": props.style
		}, { default: () => [slots.default?.({
			prev,
			next
		})] }));
		return {
			next,
			prev
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VFab/VFab.js
var makeVFabProps = propsFactory({
	app: Boolean,
	appear: Boolean,
	extended: Boolean,
	layout: Boolean,
	offset: Boolean,
	modelValue: {
		type: Boolean,
		default: true
	},
	...omit(makeVBtnProps({ active: true }), ["location"]),
	...makeLayoutItemProps(),
	...makeLocationProps(),
	...makeTransitionProps({ transition: "fab-transition" })
}, "VFab");
var VFab = genericComponent()({
	name: "VFab",
	props: makeVFabProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const height = shallowRef(56);
		const layoutItemStyles = ref();
		const { resizeRef } = useResizeObserver((entries) => {
			if (!entries.length) return;
			height.value = entries[0].target.clientHeight;
		});
		const hasPosition = toRef(() => props.app || props.absolute);
		const position = computed(() => {
			if (!hasPosition.value) return false;
			return props.location?.split(" ").shift() ?? "bottom";
		});
		const orientation = computed(() => {
			if (!hasPosition.value) return false;
			return props.location?.split(" ")[1] ?? "end";
		});
		useToggleScope(() => props.app, () => {
			const layout = useLayoutItem({
				id: props.name,
				order: computed(() => parseInt(props.order, 10)),
				position,
				layoutSize: computed(() => props.layout ? height.value + 24 : 0),
				elementSize: computed(() => height.value + 24),
				active: computed(() => props.app && model.value),
				absolute: toRef(() => props.absolute)
			});
			watchEffect(() => {
				layoutItemStyles.value = layout.layoutItemStyles.value;
			});
		});
		const vFabRef = ref();
		useRender(() => {
			const btnProps = VBtn.filterProps(props);
			return createVNode("div", {
				"ref": vFabRef,
				"class": [
					"v-fab",
					{
						"v-fab--absolute": props.absolute,
						"v-fab--app": !!props.app,
						"v-fab--extended": props.extended,
						"v-fab--offset": props.offset,
						[`v-fab--${position.value}`]: hasPosition.value,
						[`v-fab--${orientation.value}`]: hasPosition.value
					},
					props.class
				],
				"style": [props.app ? { ...layoutItemStyles.value } : { height: props.absolute ? "100%" : "inherit" }, props.style]
			}, [createVNode("div", { "class": "v-fab__container" }, [createVNode(MaybeTransition, {
				"appear": props.appear,
				"transition": props.transition
			}, { default: () => [withDirectives(createVNode(VBtn, mergeProps({ "ref": resizeRef }, btnProps, {
				"active": void 0,
				"location": void 0
			}), slots), [[vShow, props.active]])] })])]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VFileInput/VFileInput.js
var makeVFileInputProps = propsFactory({
	chips: Boolean,
	counter: Boolean,
	counterSizeString: {
		type: String,
		default: "$vuetify.fileInput.counterSize"
	},
	counterString: {
		type: String,
		default: "$vuetify.fileInput.counter"
	},
	hideInput: Boolean,
	multiple: Boolean,
	showSize: {
		type: [
			Boolean,
			Number,
			String
		],
		default: false,
		validator: (v) => {
			return typeof v === "boolean" || [1e3, 1024].includes(Number(v));
		}
	},
	...makeVInputProps({ prependIcon: "$file" }),
	modelValue: {
		type: [Array, Object],
		default: (props) => props.multiple ? [] : null,
		validator: (val) => {
			return wrapInArray(val).every((v) => v != null && typeof v === "object");
		}
	},
	...makeVFieldProps({ clearable: true })
}, "VFileInput");
var VFileInput = genericComponent()({
	name: "VFileInput",
	inheritAttrs: false,
	props: makeVFileInputProps(),
	emits: {
		"click:control": (e) => true,
		"mousedown:control": (e) => true,
		"update:focused": (focused) => true,
		"update:modelValue": (files) => true
	},
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const { t } = useLocale();
		const model = useProxiedModel(props, "modelValue", props.modelValue, (val) => wrapInArray(val), (val) => !props.multiple && Array.isArray(val) ? val[0] : val);
		const { isFocused, focus, blur } = useFocus(props);
		const base = computed(() => typeof props.showSize !== "boolean" ? props.showSize : void 0);
		const totalBytes = computed(() => (model.value ?? []).reduce((bytes, _ref2) => {
			let { size = 0 } = _ref2;
			return bytes + size;
		}, 0));
		const totalBytesReadable = computed(() => humanReadableFileSize(totalBytes.value, base.value));
		const fileNames = computed(() => (model.value ?? []).map((file) => {
			const { name = "", size = 0 } = file;
			return !props.showSize ? name : `${name} (${humanReadableFileSize(size, base.value)})`;
		}));
		const counterValue = computed(() => {
			const fileCount = model.value?.length ?? 0;
			if (props.showSize) return t(props.counterSizeString, fileCount, totalBytesReadable.value);
			else return t(props.counterString, fileCount);
		});
		const vInputRef = ref();
		const vFieldRef = ref();
		const inputRef = ref();
		const isActive = toRef(() => isFocused.value || props.active);
		const isPlainOrUnderlined = computed(() => ["plain", "underlined"].includes(props.variant));
		const isDragging = shallowRef(false);
		function onFocus() {
			if (inputRef.value !== document.activeElement) inputRef.value?.focus();
			if (!isFocused.value) focus();
		}
		function onClickPrepend(e) {
			inputRef.value?.click();
		}
		function onControlMousedown(e) {
			emit("mousedown:control", e);
		}
		function onControlClick(e) {
			inputRef.value?.click();
			emit("click:control", e);
		}
		function onClear(e) {
			e.stopPropagation();
			onFocus();
			nextTick(() => {
				model.value = [];
				callEvent(props["onClick:clear"], e);
			});
		}
		function onDragover(e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			isDragging.value = true;
		}
		function onDragleave(e) {
			e.preventDefault();
			isDragging.value = false;
		}
		function onDrop(e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			isDragging.value = false;
			if (!e.dataTransfer?.files?.length || !inputRef.value) return;
			const dataTransfer = new DataTransfer();
			for (const file of e.dataTransfer.files) dataTransfer.items.add(file);
			inputRef.value.files = dataTransfer.files;
			inputRef.value.dispatchEvent(new Event("change", { bubbles: true }));
		}
		watch(model, (newValue) => {
			if ((!Array.isArray(newValue) || !newValue.length) && inputRef.value) inputRef.value.value = "";
		});
		useRender(() => {
			const hasCounter = !!(slots.counter || props.counter);
			const hasDetails = !!(hasCounter || slots.details);
			const [rootAttrs, inputAttrs] = filterInputAttrs(attrs);
			const { modelValue: _, ...inputProps } = VInput.filterProps(props);
			const fieldProps = VField.filterProps(props);
			return createVNode(VInput, mergeProps({
				"ref": vInputRef,
				"modelValue": props.multiple ? model.value : model.value[0],
				"class": [
					"v-file-input",
					{
						"v-file-input--chips": !!props.chips,
						"v-file-input--dragging": isDragging.value,
						"v-file-input--hide": props.hideInput,
						"v-input--plain-underlined": isPlainOrUnderlined.value
					},
					props.class
				],
				"style": props.style,
				"onClick:prepend": onClickPrepend
			}, rootAttrs, inputProps, {
				"centerAffix": !isPlainOrUnderlined.value,
				"focused": isFocused.value
			}), {
				...slots,
				default: (_ref3) => {
					let { id, isDisabled, isDirty, isReadonly, isValid } = _ref3;
					return createVNode(VField, mergeProps({
						"ref": vFieldRef,
						"prepend-icon": props.prependIcon,
						"onMousedown": onControlMousedown,
						"onClick": onControlClick,
						"onClick:clear": onClear,
						"onClick:prependInner": props["onClick:prependInner"],
						"onClick:appendInner": props["onClick:appendInner"]
					}, fieldProps, {
						"id": id.value,
						"active": isActive.value || isDirty.value,
						"dirty": isDirty.value || props.dirty,
						"disabled": isDisabled.value,
						"focused": isFocused.value,
						"error": isValid.value === false,
						"onDragover": onDragover,
						"onDrop": onDrop
					}), {
						...slots,
						default: (_ref4) => {
							let { props: { class: fieldClass, ...slotProps } } = _ref4;
							return createVNode(Fragment, null, [createVNode("input", mergeProps({
								"ref": inputRef,
								"type": "file",
								"readonly": isReadonly.value,
								"disabled": isDisabled.value,
								"multiple": props.multiple,
								"name": props.name,
								"onClick": (e) => {
									e.stopPropagation();
									if (isReadonly.value) e.preventDefault();
									onFocus();
								},
								"onChange": (e) => {
									if (!e.target) return;
									model.value = [...e.target.files ?? []];
								},
								"onDragleave": onDragleave,
								"onFocus": onFocus,
								"onBlur": blur
							}, slotProps, inputAttrs), null), createVNode("div", { "class": fieldClass }, [!!model.value?.length && !props.hideInput && (slots.selection ? slots.selection({
								fileNames: fileNames.value,
								totalBytes: totalBytes.value,
								totalBytesReadable: totalBytesReadable.value
							}) : props.chips ? fileNames.value.map((text) => createVNode(VChip, {
								"key": text,
								"size": "small",
								"text": text
							}, null)) : fileNames.value.join(", "))])]);
						}
					});
				},
				details: hasDetails ? (slotProps) => createVNode(Fragment, null, [slots.details?.(slotProps), hasCounter && createVNode(Fragment, null, [createVNode("span", null, null), createVNode(VCounter, {
					"active": !!model.value?.length,
					"value": counterValue.value,
					"disabled": props.disabled
				}, slots.counter)])]) : void 0
			});
		});
		return forwardRefs({}, vInputRef, vFieldRef, inputRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VFooter/VFooter.js
var makeVFooterProps = propsFactory({
	app: Boolean,
	color: String,
	height: {
		type: [Number, String],
		default: "auto"
	},
	...makeBorderProps(),
	...makeComponentProps(),
	...makeElevationProps(),
	...makeLayoutItemProps(),
	...makeRoundedProps(),
	...makeTagProps({ tag: "footer" }),
	...makeThemeProps()
}, "VFooter");
var VFooter = genericComponent()({
	name: "VFooter",
	props: makeVFooterProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const layoutItemStyles = ref();
		const { themeClasses } = provideTheme(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { borderClasses } = useBorder(props);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		const autoHeight = shallowRef(32);
		const { resizeRef } = useResizeObserver((entries) => {
			if (!entries.length) return;
			autoHeight.value = entries[0].target.clientHeight;
		});
		const height = computed(() => props.height === "auto" ? autoHeight.value : parseInt(props.height, 10));
		useToggleScope(() => props.app, () => {
			const layout = useLayoutItem({
				id: props.name,
				order: computed(() => parseInt(props.order, 10)),
				position: toRef(() => "bottom"),
				layoutSize: height,
				elementSize: computed(() => props.height === "auto" ? void 0 : height.value),
				active: toRef(() => props.app),
				absolute: toRef(() => props.absolute)
			});
			watchEffect(() => {
				layoutItemStyles.value = layout.layoutItemStyles.value;
			});
		});
		useRender(() => createVNode(props.tag, {
			"ref": resizeRef,
			"class": [
				"v-footer",
				themeClasses.value,
				backgroundColorClasses.value,
				borderClasses.value,
				elevationClasses.value,
				roundedClasses.value,
				props.class
			],
			"style": [
				backgroundColorStyles.value,
				props.app ? layoutItemStyles.value : { height: convertToUnit(props.height) },
				props.style
			]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VForm/VForm.js
var makeVFormProps = propsFactory({
	...makeComponentProps(),
	...makeFormProps()
}, "VForm");
var VForm = genericComponent()({
	name: "VForm",
	props: makeVFormProps(),
	emits: {
		"update:modelValue": (val) => true,
		submit: (e) => true
	},
	setup(props, _ref) {
		let { slots, emit } = _ref;
		const form = createForm(props);
		const formRef = ref();
		function onReset(e) {
			e.preventDefault();
			form.reset();
		}
		function onSubmit(_e) {
			const e = _e;
			const ready = form.validate();
			e.then = ready.then.bind(ready);
			e.catch = ready.catch.bind(ready);
			e.finally = ready.finally.bind(ready);
			emit("submit", e);
			if (!e.defaultPrevented) ready.then((_ref2) => {
				let { valid } = _ref2;
				if (valid) formRef.value?.submit();
			});
			e.preventDefault();
		}
		useRender(() => createVNode("form", {
			"ref": formRef,
			"class": ["v-form", props.class],
			"style": props.style,
			"novalidate": true,
			"onReset": onReset,
			"onSubmit": onSubmit
		}, [slots.default?.(form)]));
		return forwardRefs(form, formRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VHover/VHover.js
var makeVHoverProps = propsFactory({
	disabled: Boolean,
	modelValue: {
		type: Boolean,
		default: null
	},
	...makeDelayProps()
}, "VHover");
var VHover = genericComponent()({
	name: "VHover",
	props: makeVHoverProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const isHovering = useProxiedModel(props, "modelValue");
		const { runOpenDelay, runCloseDelay } = useDelay(props, (value) => !props.disabled && (isHovering.value = value));
		return () => slots.default?.({
			isHovering: isHovering.value,
			props: {
				onMouseenter: runOpenDelay,
				onMouseleave: runCloseDelay
			}
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VInfiniteScroll/VInfiniteScroll.js
var makeVInfiniteScrollProps = propsFactory({
	color: String,
	direction: {
		type: String,
		default: "vertical",
		validator: (v) => ["vertical", "horizontal"].includes(v)
	},
	side: {
		type: String,
		default: "end",
		validator: (v) => [
			"start",
			"end",
			"both"
		].includes(v)
	},
	mode: {
		type: String,
		default: "intersect",
		validator: (v) => ["intersect", "manual"].includes(v)
	},
	margin: [Number, String],
	loadMoreText: {
		type: String,
		default: "$vuetify.infiniteScroll.loadMore"
	},
	emptyText: {
		type: String,
		default: "$vuetify.infiniteScroll.empty"
	},
	...makeDimensionProps(),
	...makeTagProps()
}, "VInfiniteScroll");
var VInfiniteScrollIntersect = defineComponent$1({
	name: "VInfiniteScrollIntersect",
	props: {
		side: {
			type: String,
			required: true
		},
		rootMargin: String
	},
	emits: { intersect: (side, isIntersecting) => true },
	setup(props, _ref) {
		let { emit } = _ref;
		const { intersectionRef, isIntersecting } = useIntersectionObserver();
		watch(isIntersecting, async (val) => {
			emit("intersect", props.side, val);
		});
		useRender(() => createVNode("div", {
			"class": "v-infinite-scroll-intersect",
			"style": { "--v-infinite-margin-size": props.rootMargin },
			"ref": intersectionRef
		}, [createTextVNode("\xA0")]));
		return {};
	}
});
var VInfiniteScroll = genericComponent()({
	name: "VInfiniteScroll",
	props: makeVInfiniteScrollProps(),
	emits: { load: (options) => true },
	setup(props, _ref2) {
		let { slots, emit } = _ref2;
		const rootEl = ref();
		const startStatus = shallowRef("ok");
		const endStatus = shallowRef("ok");
		const margin = computed(() => convertToUnit(props.margin));
		const isIntersecting = shallowRef(false);
		function setScrollAmount(amount) {
			if (!rootEl.value) return;
			const property = props.direction === "vertical" ? "scrollTop" : "scrollLeft";
			rootEl.value[property] = amount;
		}
		function getScrollAmount() {
			if (!rootEl.value) return 0;
			const property = props.direction === "vertical" ? "scrollTop" : "scrollLeft";
			return rootEl.value[property];
		}
		function getScrollSize() {
			if (!rootEl.value) return 0;
			const property = props.direction === "vertical" ? "scrollHeight" : "scrollWidth";
			return rootEl.value[property];
		}
		function getContainerSize() {
			if (!rootEl.value) return 0;
			const property = props.direction === "vertical" ? "clientHeight" : "clientWidth";
			return rootEl.value[property];
		}
		onMounted(() => {
			if (!rootEl.value) return;
			if (props.side === "start") setScrollAmount(getScrollSize());
			else if (props.side === "both") setScrollAmount(getScrollSize() / 2 - getContainerSize() / 2);
		});
		function setStatus(side, status) {
			if (side === "start") startStatus.value = status;
			else if (side === "end") endStatus.value = status;
		}
		function getStatus(side) {
			return side === "start" ? startStatus.value : endStatus.value;
		}
		let previousScrollSize = 0;
		function handleIntersect(side, _isIntersecting) {
			isIntersecting.value = _isIntersecting;
			if (isIntersecting.value) intersecting(side);
		}
		function intersecting(side) {
			if (props.mode !== "manual" && !isIntersecting.value) return;
			const status = getStatus(side);
			if (!rootEl.value || ["empty", "loading"].includes(status)) return;
			previousScrollSize = getScrollSize();
			setStatus(side, "loading");
			function done(status) {
				setStatus(side, status);
				nextTick(() => {
					if (status === "empty" || status === "error") return;
					if (status === "ok" && side === "start") setScrollAmount(getScrollSize() - previousScrollSize + getScrollAmount());
					if (props.mode !== "manual") nextTick(() => {
						window.requestAnimationFrame(() => {
							window.requestAnimationFrame(() => {
								window.requestAnimationFrame(() => {
									intersecting(side);
								});
							});
						});
					});
				});
			}
			emit("load", {
				side,
				done
			});
		}
		const { t } = useLocale();
		function renderSide(side, status) {
			if (props.side !== side && props.side !== "both") return;
			const onClick = () => intersecting(side);
			const slotProps = {
				side,
				props: {
					onClick,
					color: props.color
				}
			};
			if (status === "error") return slots.error?.(slotProps);
			if (status === "empty") return slots.empty?.(slotProps) ?? createVNode("div", null, [t(props.emptyText)]);
			if (props.mode === "manual") {
				if (status === "loading") return slots.loading?.(slotProps) ?? createVNode(VProgressCircular, {
					"indeterminate": true,
					"color": props.color
				}, null);
				return slots["load-more"]?.(slotProps) ?? createVNode(VBtn, {
					"variant": "outlined",
					"color": props.color,
					"onClick": onClick
				}, { default: () => [t(props.loadMoreText)] });
			}
			return slots.loading?.(slotProps) ?? createVNode(VProgressCircular, {
				"indeterminate": true,
				"color": props.color
			}, null);
		}
		const { dimensionStyles } = useDimension(props);
		useRender(() => {
			const Tag = props.tag;
			const hasStartIntersect = props.side === "start" || props.side === "both";
			const hasEndIntersect = props.side === "end" || props.side === "both";
			const intersectMode = props.mode === "intersect";
			return createVNode(Tag, {
				"ref": rootEl,
				"class": [
					"v-infinite-scroll",
					`v-infinite-scroll--${props.direction}`,
					{
						"v-infinite-scroll--start": hasStartIntersect,
						"v-infinite-scroll--end": hasEndIntersect
					}
				],
				"style": dimensionStyles.value
			}, { default: () => [
				createVNode("div", { "class": "v-infinite-scroll__side" }, [renderSide("start", startStatus.value)]),
				hasStartIntersect && intersectMode && createVNode(VInfiniteScrollIntersect, {
					"key": "start",
					"side": "start",
					"onIntersect": handleIntersect,
					"rootMargin": margin.value
				}, null),
				slots.default?.(),
				hasEndIntersect && intersectMode && createVNode(VInfiniteScrollIntersect, {
					"key": "end",
					"side": "end",
					"onIntersect": handleIntersect,
					"rootMargin": margin.value
				}, null),
				createVNode("div", { "class": "v-infinite-scroll__side" }, [renderSide("end", endStatus.value)])
			] });
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VItemGroup/VItemGroup.js
var VItemGroupSymbol = Symbol.for("vuetify:v-item-group");
var makeVItemGroupProps = propsFactory({
	...makeComponentProps(),
	...makeGroupProps({ selectedClass: "v-item--selected" }),
	...makeTagProps(),
	...makeThemeProps()
}, "VItemGroup");
var VItemGroup = genericComponent()({
	name: "VItemGroup",
	props: makeVItemGroupProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { isSelected, select, next, prev, selected } = useGroup(props, VItemGroupSymbol);
		return () => createVNode(props.tag, {
			"class": [
				"v-item-group",
				themeClasses.value,
				props.class
			],
			"style": props.style
		}, { default: () => [slots.default?.({
			isSelected,
			select,
			next,
			prev,
			selected: selected.value
		})] });
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VItemGroup/VItem.js
var VItem = genericComponent()({
	name: "VItem",
	props: makeGroupItemProps(),
	emits: { "group:selected": (val) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { isSelected, select, toggle, selectedClass, value, disabled } = useGroupItem(props, VItemGroupSymbol);
		return () => slots.default?.({
			isSelected: isSelected.value,
			selectedClass: selectedClass.value,
			select,
			toggle,
			value: value.value,
			disabled: disabled.value
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VKbd/index.js
var VKbd = createSimpleFunctional("v-kbd", "kbd");
//#endregion
//#region node_modules/vuetify/lib/components/VLayout/VLayout.js
var makeVLayoutProps = propsFactory({
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeLayoutProps()
}, "VLayout");
var VLayout = genericComponent()({
	name: "VLayout",
	props: makeVLayoutProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { layoutClasses, layoutStyles, getLayoutItem, items, layoutRef } = createLayout(props);
		const { dimensionStyles } = useDimension(props);
		useRender(() => createVNode("div", {
			"ref": layoutRef,
			"class": [layoutClasses.value, props.class],
			"style": [
				dimensionStyles.value,
				layoutStyles.value,
				props.style
			]
		}, [slots.default?.()]));
		return {
			getLayoutItem,
			items
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VLayout/VLayoutItem.js
var makeVLayoutItemProps = propsFactory({
	position: {
		type: String,
		required: true
	},
	size: {
		type: [Number, String],
		default: 300
	},
	modelValue: Boolean,
	...makeComponentProps(),
	...makeLayoutItemProps()
}, "VLayoutItem");
var VLayoutItem = genericComponent()({
	name: "VLayoutItem",
	props: makeVLayoutItemProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { layoutItemStyles } = useLayoutItem({
			id: props.name,
			order: computed(() => parseInt(props.order, 10)),
			position: toRef(() => props.position),
			elementSize: toRef(() => props.size),
			layoutSize: toRef(() => props.size),
			active: toRef(() => props.modelValue),
			absolute: toRef(() => props.absolute)
		});
		return () => createVNode("div", {
			"class": ["v-layout-item", props.class],
			"style": [layoutItemStyles.value, props.style]
		}, [slots.default?.()]);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VLazy/VLazy.js
var makeVLazyProps = propsFactory({
	modelValue: Boolean,
	options: {
		type: Object,
		default: () => ({
			root: void 0,
			rootMargin: void 0,
			threshold: void 0
		})
	},
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeTagProps(),
	...makeTransitionProps({ transition: "fade-transition" })
}, "VLazy");
var VLazy = genericComponent()({
	name: "VLazy",
	directives: { intersect: Intersect },
	props: makeVLazyProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { dimensionStyles } = useDimension(props);
		const isActive = useProxiedModel(props, "modelValue");
		function onIntersect(isIntersecting) {
			if (isActive.value) return;
			isActive.value = isIntersecting;
		}
		useRender(() => withDirectives(createVNode(props.tag, {
			"class": ["v-lazy", props.class],
			"style": [dimensionStyles.value, props.style]
		}, { default: () => [isActive.value && createVNode(MaybeTransition, {
			"transition": props.transition,
			"appear": true
		}, { default: () => [slots.default?.()] })] }), [[
			resolveDirective("intersect"),
			{
				handler: onIntersect,
				options: props.options
			},
			null
		]]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VLocaleProvider/VLocaleProvider.js
var makeVLocaleProviderProps = propsFactory({
	locale: String,
	fallbackLocale: String,
	messages: Object,
	rtl: {
		type: Boolean,
		default: void 0
	},
	...makeComponentProps()
}, "VLocaleProvider");
var VLocaleProvider = genericComponent()({
	name: "VLocaleProvider",
	props: makeVLocaleProviderProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { rtlClasses } = provideLocale(props);
		useRender(() => createVNode("div", {
			"class": [
				"v-locale-provider",
				rtlClasses.value,
				props.class
			],
			"style": props.style
		}, [slots.default?.()]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VMain/VMain.js
var makeVMainProps = propsFactory({
	scrollable: Boolean,
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeTagProps({ tag: "main" })
}, "VMain");
var VMain = genericComponent()({
	name: "VMain",
	props: makeVMainProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { dimensionStyles } = useDimension(props);
		const { mainStyles } = useLayout();
		const { ssrBootStyles } = useSsrBoot();
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-main",
				{ "v-main--scrollable": props.scrollable },
				props.class
			],
			"style": [
				mainStyles.value,
				ssrBootStyles.value,
				dimensionStyles.value,
				props.style
			]
		}, { default: () => [props.scrollable ? createVNode("div", { "class": "v-main__scroller" }, [slots.default?.()]) : slots.default?.()] }));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VNavigationDrawer/sticky.js
function useSticky(_ref) {
	let { rootEl, isSticky, layoutItemStyles } = _ref;
	const isStuck = shallowRef(false);
	const stuckPosition = shallowRef(0);
	const stickyStyles = computed(() => {
		const side = typeof isStuck.value === "boolean" ? "top" : isStuck.value;
		return [isSticky.value ? {
			top: "auto",
			bottom: "auto",
			height: void 0
		} : void 0, isStuck.value ? { [side]: convertToUnit(stuckPosition.value) } : { top: layoutItemStyles.value.top }];
	});
	onMounted(() => {
		watch(isSticky, (val) => {
			if (val) window.addEventListener("scroll", onScroll, { passive: true });
			else window.removeEventListener("scroll", onScroll);
		}, { immediate: true });
	});
	onBeforeUnmount(() => {
		window.removeEventListener("scroll", onScroll);
	});
	let lastScrollTop = 0;
	function onScroll() {
		const direction = lastScrollTop > window.scrollY ? "up" : "down";
		const rect = rootEl.value.getBoundingClientRect();
		const layoutTop = parseFloat(layoutItemStyles.value.top ?? 0);
		const top = window.scrollY - Math.max(0, stuckPosition.value - layoutTop);
		const bottom = rect.height + Math.max(stuckPosition.value, layoutTop) - window.scrollY - window.innerHeight;
		const bodyScroll = parseFloat(getComputedStyle(rootEl.value).getPropertyValue("--v-body-scroll-y")) || 0;
		if (rect.height < window.innerHeight - layoutTop) {
			isStuck.value = "top";
			stuckPosition.value = layoutTop;
		} else if (direction === "up" && isStuck.value === "bottom" || direction === "down" && isStuck.value === "top") {
			stuckPosition.value = window.scrollY + rect.top - bodyScroll;
			isStuck.value = true;
		} else if (direction === "down" && bottom <= 0) {
			stuckPosition.value = 0;
			isStuck.value = "bottom";
		} else if (direction === "up" && top <= 0) {
			if (!bodyScroll) {
				stuckPosition.value = rect.top + top;
				isStuck.value = "top";
			} else if (isStuck.value !== "top") {
				stuckPosition.value = -top + bodyScroll + layoutTop;
				isStuck.value = "top";
			}
		}
		lastScrollTop = window.scrollY;
	}
	return {
		isStuck,
		stickyStyles
	};
}
//#endregion
//#region node_modules/vuetify/lib/composables/touch.js
var HORIZON = 100;
var HISTORY = 20;
/** @see https://android.googlesource.com/platform/frameworks/native/+/master/libs/input/VelocityTracker.cpp */
function kineticEnergyToVelocity(work) {
	return (work < 0 ? -1 : 1) * Math.sqrt(Math.abs(work)) * 1.41421356237;
}
/**
* Returns pointer velocity in px/s
*/
function calculateImpulseVelocity(samples) {
	if (samples.length < 2) return 0;
	if (samples.length === 2) {
		if (samples[1].t === samples[0].t) return 0;
		return (samples[1].d - samples[0].d) / (samples[1].t - samples[0].t);
	}
	let work = 0;
	for (let i = samples.length - 1; i > 0; i--) {
		if (samples[i].t === samples[i - 1].t) continue;
		const vprev = kineticEnergyToVelocity(work);
		const vcurr = (samples[i].d - samples[i - 1].d) / (samples[i].t - samples[i - 1].t);
		work += (vcurr - vprev) * Math.abs(vcurr);
		if (i === samples.length - 1) work *= .5;
	}
	return kineticEnergyToVelocity(work) * 1e3;
}
function useVelocity() {
	const touches = {};
	function addMovement(e) {
		Array.from(e.changedTouches).forEach((touch) => {
			(touches[touch.identifier] ?? (touches[touch.identifier] = new CircularBuffer(HISTORY))).push([e.timeStamp, touch]);
		});
	}
	function endTouch(e) {
		Array.from(e.changedTouches).forEach((touch) => {
			delete touches[touch.identifier];
		});
	}
	function getVelocity(id) {
		const samples = touches[id]?.values().reverse();
		if (!samples) throw new Error(`No samples for touch id ${id}`);
		const newest = samples[0];
		const x = [];
		const y = [];
		for (const val of samples) {
			if (newest[0] - val[0] > HORIZON) break;
			x.push({
				t: val[0],
				d: val[1].clientX
			});
			y.push({
				t: val[0],
				d: val[1].clientY
			});
		}
		return {
			x: calculateImpulseVelocity(x),
			y: calculateImpulseVelocity(y),
			get direction() {
				const { x, y } = this;
				const [absX, absY] = [Math.abs(x), Math.abs(y)];
				return absX > absY && x >= 0 ? "right" : absX > absY && x <= 0 ? "left" : absY > absX && y >= 0 ? "down" : absY > absX && y <= 0 ? "up" : oops$1();
			}
		};
	}
	return {
		addMovement,
		endTouch,
		getVelocity
	};
}
function oops$1() {
	throw new Error();
}
//#endregion
//#region node_modules/vuetify/lib/components/VNavigationDrawer/touch.js
function useTouch(_ref) {
	let { el, isActive, isTemporary, width, touchless, position } = _ref;
	onMounted(() => {
		window.addEventListener("touchstart", onTouchstart, { passive: true });
		window.addEventListener("touchmove", onTouchmove, { passive: false });
		window.addEventListener("touchend", onTouchend, { passive: true });
	});
	onBeforeUnmount(() => {
		window.removeEventListener("touchstart", onTouchstart);
		window.removeEventListener("touchmove", onTouchmove);
		window.removeEventListener("touchend", onTouchend);
	});
	const isHorizontal = computed(() => ["left", "right"].includes(position.value));
	const { addMovement, endTouch, getVelocity } = useVelocity();
	let maybeDragging = false;
	const isDragging = shallowRef(false);
	const dragProgress = shallowRef(0);
	const offset = shallowRef(0);
	let start;
	function getOffset(pos, active) {
		return (position.value === "left" ? pos : position.value === "right" ? document.documentElement.clientWidth - pos : position.value === "top" ? pos : position.value === "bottom" ? document.documentElement.clientHeight - pos : oops()) - (active ? width.value : 0);
	}
	function getProgress(pos) {
		let limit = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : true;
		const progress = position.value === "left" ? (pos - offset.value) / width.value : position.value === "right" ? (document.documentElement.clientWidth - pos - offset.value) / width.value : position.value === "top" ? (pos - offset.value) / width.value : position.value === "bottom" ? (document.documentElement.clientHeight - pos - offset.value) / width.value : oops();
		return limit ? Math.max(0, Math.min(1, progress)) : progress;
	}
	function onTouchstart(e) {
		if (touchless.value) return;
		const touchX = e.changedTouches[0].clientX;
		const touchY = e.changedTouches[0].clientY;
		const touchZone = 25;
		const inTouchZone = position.value === "left" ? touchX < touchZone : position.value === "right" ? touchX > document.documentElement.clientWidth - touchZone : position.value === "top" ? touchY < touchZone : position.value === "bottom" ? touchY > document.documentElement.clientHeight - touchZone : oops();
		const inElement = isActive.value && (position.value === "left" ? touchX < width.value : position.value === "right" ? touchX > document.documentElement.clientWidth - width.value : position.value === "top" ? touchY < width.value : position.value === "bottom" ? touchY > document.documentElement.clientHeight - width.value : oops());
		if (inTouchZone || inElement || isActive.value && isTemporary.value) {
			start = [touchX, touchY];
			offset.value = getOffset(isHorizontal.value ? touchX : touchY, isActive.value);
			dragProgress.value = getProgress(isHorizontal.value ? touchX : touchY);
			maybeDragging = offset.value > -20 && offset.value < 80;
			endTouch(e);
			addMovement(e);
		}
	}
	function onTouchmove(e) {
		const touchX = e.changedTouches[0].clientX;
		const touchY = e.changedTouches[0].clientY;
		if (maybeDragging) {
			if (!e.cancelable) {
				maybeDragging = false;
				return;
			}
			const dx = Math.abs(touchX - start[0]);
			const dy = Math.abs(touchY - start[1]);
			if (isHorizontal.value ? dx > dy && dx > 3 : dy > dx && dy > 3) {
				isDragging.value = true;
				maybeDragging = false;
			} else if ((isHorizontal.value ? dy : dx) > 3) maybeDragging = false;
		}
		if (!isDragging.value) return;
		e.preventDefault();
		addMovement(e);
		const progress = getProgress(isHorizontal.value ? touchX : touchY, false);
		dragProgress.value = Math.max(0, Math.min(1, progress));
		if (progress > 1) offset.value = getOffset(isHorizontal.value ? touchX : touchY, true);
		else if (progress < 0) offset.value = getOffset(isHorizontal.value ? touchX : touchY, false);
	}
	function onTouchend(e) {
		maybeDragging = false;
		if (!isDragging.value) return;
		addMovement(e);
		isDragging.value = false;
		const velocity = getVelocity(e.changedTouches[0].identifier);
		const vx = Math.abs(velocity.x);
		const vy = Math.abs(velocity.y);
		if (isHorizontal.value ? vx > vy && vx > 400 : vy > vx && vy > 3) isActive.value = velocity.direction === ({
			left: "right",
			right: "left",
			top: "down",
			bottom: "up"
		}[position.value] || oops());
		else isActive.value = dragProgress.value > .5;
	}
	const dragStyles = computed(() => {
		return isDragging.value ? {
			transform: position.value === "left" ? `translateX(calc(-100% + ${dragProgress.value * width.value}px))` : position.value === "right" ? `translateX(calc(100% - ${dragProgress.value * width.value}px))` : position.value === "top" ? `translateY(calc(-100% + ${dragProgress.value * width.value}px))` : position.value === "bottom" ? `translateY(calc(100% - ${dragProgress.value * width.value}px))` : oops(),
			transition: "none"
		} : void 0;
	});
	useToggleScope(isDragging, () => {
		const transform = el.value?.style.transform ?? null;
		const transition = el.value?.style.transition ?? null;
		watchEffect(() => {
			el.value?.style.setProperty("transform", dragStyles.value?.transform || "none");
			el.value?.style.setProperty("transition", dragStyles.value?.transition || null);
		});
		onScopeDispose(() => {
			el.value?.style.setProperty("transform", transform);
			el.value?.style.setProperty("transition", transition);
		});
	});
	return {
		isDragging,
		dragProgress,
		dragStyles
	};
}
function oops() {
	throw new Error();
}
//#endregion
//#region node_modules/vuetify/lib/components/VNavigationDrawer/VNavigationDrawer.js
var locations = [
	"start",
	"end",
	"left",
	"right",
	"top",
	"bottom"
];
var makeVNavigationDrawerProps = propsFactory({
	color: String,
	disableResizeWatcher: Boolean,
	disableRouteWatcher: Boolean,
	expandOnHover: Boolean,
	floating: Boolean,
	modelValue: {
		type: Boolean,
		default: null
	},
	permanent: Boolean,
	rail: {
		type: Boolean,
		default: null
	},
	railWidth: {
		type: [Number, String],
		default: 56
	},
	scrim: {
		type: [Boolean, String],
		default: true
	},
	image: String,
	temporary: Boolean,
	persistent: Boolean,
	touchless: Boolean,
	width: {
		type: [Number, String],
		default: 256
	},
	location: {
		type: String,
		default: "start",
		validator: (value) => locations.includes(value)
	},
	sticky: Boolean,
	...makeBorderProps(),
	...makeComponentProps(),
	...makeDelayProps(),
	...makeDisplayProps({ mobile: null }),
	...makeElevationProps(),
	...makeLayoutItemProps(),
	...makeRoundedProps(),
	...makeTagProps({ tag: "nav" }),
	...makeThemeProps()
}, "VNavigationDrawer");
var VNavigationDrawer = genericComponent()({
	name: "VNavigationDrawer",
	props: makeVNavigationDrawerProps(),
	emits: {
		"update:modelValue": (val) => true,
		"update:rail": (val) => true
	},
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const { isRtl } = useRtl();
		const { themeClasses } = provideTheme(props);
		const { borderClasses } = useBorder(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { elevationClasses } = useElevation(props);
		const { displayClasses, mobile } = useDisplay(props);
		const { roundedClasses } = useRounded(props);
		const router = useRouter();
		const isActive = useProxiedModel(props, "modelValue", null, (v) => !!v);
		const { ssrBootStyles } = useSsrBoot();
		const { scopeId } = useScopeId();
		const rootEl = ref();
		const isHovering = shallowRef(false);
		const { runOpenDelay, runCloseDelay } = useDelay(props, (value) => {
			isHovering.value = value;
		});
		const width = computed(() => {
			return props.rail && props.expandOnHover && isHovering.value ? Number(props.width) : Number(props.rail ? props.railWidth : props.width);
		});
		const location = computed(() => {
			return toPhysical(props.location, isRtl.value);
		});
		const isPersistent = toRef(() => props.persistent);
		const isTemporary = computed(() => !props.permanent && (mobile.value || props.temporary));
		const isSticky = computed(() => props.sticky && !isTemporary.value && location.value !== "bottom");
		useToggleScope(() => props.expandOnHover && props.rail != null, () => {
			watch(isHovering, (val) => emit("update:rail", !val));
		});
		useToggleScope(() => !props.disableResizeWatcher, () => {
			watch(isTemporary, (val) => !props.permanent && nextTick(() => isActive.value = !val));
		});
		useToggleScope(() => !props.disableRouteWatcher && !!router, () => {
			watch(router.currentRoute, () => isTemporary.value && (isActive.value = false));
		});
		watch(() => props.permanent, (val) => {
			if (val) isActive.value = true;
		});
		if (props.modelValue == null && !isTemporary.value) isActive.value = props.permanent || !mobile.value;
		const { isDragging, dragProgress } = useTouch({
			el: rootEl,
			isActive,
			isTemporary,
			width,
			touchless: toRef(() => props.touchless),
			position: location
		});
		const layoutSize = computed(() => {
			const size = isTemporary.value ? 0 : props.rail && props.expandOnHover ? Number(props.railWidth) : width.value;
			return isDragging.value ? size * dragProgress.value : size;
		});
		const { layoutItemStyles, layoutItemScrimStyles } = useLayoutItem({
			id: props.name,
			order: computed(() => parseInt(props.order, 10)),
			position: location,
			layoutSize,
			elementSize: width,
			active: readonly(isActive),
			disableTransitions: toRef(() => isDragging.value),
			absolute: computed(() => props.absolute || isSticky.value && typeof isStuck.value !== "string")
		});
		const { isStuck, stickyStyles } = useSticky({
			rootEl,
			isSticky,
			layoutItemStyles
		});
		const scrimColor = useBackgroundColor(() => {
			return typeof props.scrim === "string" ? props.scrim : null;
		});
		const scrimStyles = computed(() => ({
			...isDragging.value ? {
				opacity: dragProgress.value * .2,
				transition: "none"
			} : void 0,
			...layoutItemScrimStyles.value
		}));
		provideDefaults({ VList: { bgColor: "transparent" } });
		useRender(() => {
			const hasImage = slots.image || props.image;
			return createVNode(Fragment, null, [createVNode(props.tag, mergeProps({
				"ref": rootEl,
				"onMouseenter": runOpenDelay,
				"onMouseleave": runCloseDelay,
				"class": [
					"v-navigation-drawer",
					`v-navigation-drawer--${location.value}`,
					{
						"v-navigation-drawer--expand-on-hover": props.expandOnHover,
						"v-navigation-drawer--floating": props.floating,
						"v-navigation-drawer--is-hovering": isHovering.value,
						"v-navigation-drawer--rail": props.rail,
						"v-navigation-drawer--temporary": isTemporary.value,
						"v-navigation-drawer--persistent": isPersistent.value,
						"v-navigation-drawer--active": isActive.value,
						"v-navigation-drawer--sticky": isSticky.value
					},
					themeClasses.value,
					backgroundColorClasses.value,
					borderClasses.value,
					displayClasses.value,
					elevationClasses.value,
					roundedClasses.value,
					props.class
				],
				"style": [
					backgroundColorStyles.value,
					layoutItemStyles.value,
					ssrBootStyles.value,
					stickyStyles.value,
					props.style
				]
			}, scopeId, attrs), { default: () => [
				hasImage && createVNode("div", {
					"key": "image",
					"class": "v-navigation-drawer__img"
				}, [!slots.image ? createVNode(VImg, {
					"key": "image-img",
					"alt": "",
					"cover": true,
					"height": "inherit",
					"src": props.image
				}, null) : createVNode(VDefaultsProvider, {
					"key": "image-defaults",
					"disabled": !props.image,
					"defaults": { VImg: {
						alt: "",
						cover: true,
						height: "inherit",
						src: props.image
					} }
				}, slots.image)]),
				slots.prepend && createVNode("div", { "class": "v-navigation-drawer__prepend" }, [slots.prepend?.()]),
				createVNode("div", { "class": "v-navigation-drawer__content" }, [slots.default?.()]),
				slots.append && createVNode("div", { "class": "v-navigation-drawer__append" }, [slots.append?.()])
			] }), createVNode(Transition, { "name": "fade-transition" }, { default: () => [isTemporary.value && (isDragging.value || isActive.value) && !!props.scrim && createVNode("div", mergeProps({
				"class": ["v-navigation-drawer__scrim", scrimColor.backgroundColorClasses.value],
				"style": [scrimStyles.value, scrimColor.backgroundColorStyles.value],
				"onClick": () => {
					if (isPersistent.value) return;
					isActive.value = false;
				}
			}, scopeId), null)] })]);
		});
		return { isStuck };
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VNoSsr/VNoSsr.js
var VNoSsr = defineComponent$1({
	name: "VNoSsr",
	setup(_, _ref) {
		let { slots } = _ref;
		const show = useHydration();
		return () => show.value && slots.default?.();
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VNumberInput/hold.js
var HOLD_REPEAT = 50;
var HOLD_DELAY = 500;
function useHold(_ref) {
	let { toggleUpDown } = _ref;
	let timeout = -1;
	let interval = -1;
	onScopeDispose(holdStop);
	function holdStart(value) {
		holdStop();
		tick(value);
		timeout = window.setTimeout(() => {
			interval = window.setInterval(() => tick(value), HOLD_REPEAT);
		}, HOLD_DELAY);
	}
	function holdStop() {
		window.clearTimeout(timeout);
		window.clearInterval(interval);
	}
	function tick(value) {
		toggleUpDown(value === "up");
	}
	return {
		holdStart,
		holdStop
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VNumberInput/VNumberInput.js
var makeVNumberInputProps = propsFactory({
	controlVariant: {
		type: String,
		default: "default"
	},
	inset: Boolean,
	hideInput: Boolean,
	modelValue: {
		type: Number,
		default: null
	},
	min: {
		type: Number,
		default: Number.MIN_SAFE_INTEGER
	},
	max: {
		type: Number,
		default: Number.MAX_SAFE_INTEGER
	},
	step: {
		type: Number,
		default: 1
	},
	precision: {
		type: Number,
		default: 0
	},
	...omit(makeVTextFieldProps(), ["modelValue", "validationValue"])
}, "VNumberInput");
var VNumberInput = genericComponent()({
	name: "VNumberInput",
	props: { ...makeVNumberInputProps() },
	emits: { "update:modelValue": (val) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const vTextFieldRef = ref();
		const { holdStart, holdStop } = useHold({ toggleUpDown });
		const form = useForm$1(props);
		const controlsDisabled = computed(() => form.isDisabled.value || form.isReadonly.value);
		const { isFocused, focus, blur } = useFocus(props);
		function correctPrecision(val) {
			let precision = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : props.precision;
			const fixed = precision == null ? String(val) : val.toFixed(precision);
			return isFocused.value ? Number(fixed).toString() : fixed;
		}
		const model = useProxiedModel(props, "modelValue", null, (val) => val ?? null, (val) => val == null ? val ?? null : clamp(Number(val), props.min, props.max));
		const _inputText = shallowRef(null);
		watchEffect(() => {
			if (isFocused.value && !controlsDisabled.value) {} else if (model.value == null) _inputText.value = null;
			else if (!isNaN(model.value)) _inputText.value = correctPrecision(model.value);
		});
		const inputText = computed({
			get: () => _inputText.value,
			set(val) {
				if (val === null || val === "") {
					model.value = null;
					_inputText.value = null;
				} else if (!isNaN(Number(val)) && Number(val) <= props.max && Number(val) >= props.min) {
					model.value = Number(val);
					_inputText.value = val;
				}
			}
		});
		const canIncrease = computed(() => {
			if (controlsDisabled.value) return false;
			return (model.value ?? 0) + props.step <= props.max;
		});
		const canDecrease = computed(() => {
			if (controlsDisabled.value) return false;
			return (model.value ?? 0) - props.step >= props.min;
		});
		const controlVariant = computed(() => {
			return props.hideInput ? "stacked" : props.controlVariant;
		});
		const incrementIcon = toRef(() => controlVariant.value === "split" ? "$plus" : "$collapse");
		const decrementIcon = toRef(() => controlVariant.value === "split" ? "$minus" : "$expand");
		const controlNodeSize = toRef(() => controlVariant.value === "split" ? "default" : "small");
		const controlNodeDefaultHeight = toRef(() => controlVariant.value === "stacked" ? "auto" : "100%");
		const incrementSlotProps = { props: {
			onClick: onControlClick,
			onPointerup: onControlMouseup,
			onPointerdown: onUpControlMousedown
		} };
		const decrementSlotProps = { props: {
			onClick: onControlClick,
			onPointerup: onControlMouseup,
			onPointerdown: onDownControlMousedown
		} };
		watch(() => props.precision, () => formatInputValue());
		onMounted(() => {
			clampModel();
		});
		function inferPrecision(value) {
			if (value == null) return 0;
			const str = value.toString();
			const idx = str.indexOf(".");
			return ~idx ? str.length - idx : 0;
		}
		function toggleUpDown() {
			let increment = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : true;
			if (controlsDisabled.value) return;
			if (model.value == null) {
				inputText.value = correctPrecision(clamp(0, props.min, props.max));
				return;
			}
			let inferredPrecision = Math.max(inferPrecision(model.value), inferPrecision(props.step));
			if (props.precision != null) inferredPrecision = Math.max(inferredPrecision, props.precision);
			if (increment) {
				if (canIncrease.value) inputText.value = correctPrecision(model.value + props.step, inferredPrecision);
			} else if (canDecrease.value) inputText.value = correctPrecision(model.value - props.step, inferredPrecision);
		}
		function onBeforeinput(e) {
			if (!e.data) return;
			const existingTxt = e.target?.value;
			const selectionStart = e.target?.selectionStart;
			const selectionEnd = e.target?.selectionEnd;
			const potentialNewInputVal = existingTxt ? existingTxt.slice(0, selectionStart) + e.data + existingTxt.slice(selectionEnd) : e.data;
			if (!/^-?(\d+(\.\d*)?|(\.\d+)|\d*|\.)$/.test(potentialNewInputVal)) e.preventDefault();
			if (props.precision == null) return;
			if (potentialNewInputVal.split(".")[1]?.length > props.precision) e.preventDefault();
			if (props.precision === 0 && potentialNewInputVal.includes(".")) e.preventDefault();
		}
		async function onKeydown(e) {
			if ([
				"Enter",
				"ArrowLeft",
				"ArrowRight",
				"Backspace",
				"Delete",
				"Tab"
			].includes(e.key) || e.ctrlKey) return;
			if (["ArrowDown", "ArrowUp"].includes(e.key)) {
				e.preventDefault();
				clampModel();
				await nextTick();
				if (e.key === "ArrowDown") toggleUpDown(false);
				else toggleUpDown();
			}
		}
		function onControlClick(e) {
			e.stopPropagation();
		}
		function onControlMouseup(e) {
			e.currentTarget?.releasePointerCapture(e.pointerId);
			e.preventDefault();
			e.stopPropagation();
			holdStop();
		}
		function onUpControlMousedown(e) {
			e.currentTarget?.setPointerCapture(e.pointerId);
			e.preventDefault();
			e.stopPropagation();
			holdStart("up");
		}
		function onDownControlMousedown(e) {
			e.currentTarget?.setPointerCapture(e.pointerId);
			e.preventDefault();
			e.stopPropagation();
			holdStart("down");
		}
		function clampModel() {
			if (controlsDisabled.value) return;
			if (!vTextFieldRef.value) return;
			const actualText = vTextFieldRef.value.value;
			if (actualText && !isNaN(Number(actualText))) inputText.value = correctPrecision(clamp(Number(actualText), props.min, props.max));
			else inputText.value = null;
		}
		function formatInputValue() {
			if (controlsDisabled.value) return;
			if (model.value === null || isNaN(model.value)) {
				inputText.value = null;
				return;
			}
			inputText.value = props.precision == null ? String(model.value) : model.value.toFixed(props.precision);
		}
		function trimDecimalZeros() {
			if (controlsDisabled.value) return;
			if (model.value === null || isNaN(model.value)) {
				inputText.value = null;
				return;
			}
			inputText.value = model.value.toString();
		}
		function onFocus() {
			focus();
			trimDecimalZeros();
		}
		function onBlur() {
			blur();
			clampModel();
		}
		useRender(() => {
			const { modelValue: _, ...textFieldProps } = VTextField.filterProps(props);
			function incrementControlNode() {
				return !slots.increment ? createVNode(VBtn, {
					"disabled": !canIncrease.value,
					"flat": true,
					"key": "increment-btn",
					"height": controlNodeDefaultHeight.value,
					"data-testid": "increment",
					"aria-hidden": "true",
					"icon": incrementIcon.value,
					"onClick": onControlClick,
					"onPointerup": onControlMouseup,
					"onPointerdown": onUpControlMousedown,
					"size": controlNodeSize.value,
					"tabindex": "-1"
				}, null) : createVNode(VDefaultsProvider, {
					"key": "increment-defaults",
					"defaults": { VBtn: {
						disabled: !canIncrease.value,
						flat: true,
						height: controlNodeDefaultHeight.value,
						size: controlNodeSize.value,
						icon: incrementIcon.value
					} }
				}, { default: () => [slots.increment(incrementSlotProps)] });
			}
			function decrementControlNode() {
				return !slots.decrement ? createVNode(VBtn, {
					"disabled": !canDecrease.value,
					"flat": true,
					"key": "decrement-btn",
					"height": controlNodeDefaultHeight.value,
					"data-testid": "decrement",
					"aria-hidden": "true",
					"icon": decrementIcon.value,
					"size": controlNodeSize.value,
					"tabindex": "-1",
					"onClick": onControlClick,
					"onPointerup": onControlMouseup,
					"onPointerdown": onDownControlMousedown
				}, null) : createVNode(VDefaultsProvider, {
					"key": "decrement-defaults",
					"defaults": { VBtn: {
						disabled: !canDecrease.value,
						flat: true,
						height: controlNodeDefaultHeight.value,
						size: controlNodeSize.value,
						icon: decrementIcon.value
					} }
				}, { default: () => [slots.decrement(decrementSlotProps)] });
			}
			function controlNode() {
				return createVNode("div", { "class": "v-number-input__control" }, [
					decrementControlNode(),
					createVNode(VDivider, { "vertical": controlVariant.value !== "stacked" }, null),
					incrementControlNode()
				]);
			}
			function dividerNode() {
				return !props.hideInput && !props.inset ? createVNode(VDivider, { "vertical": true }, null) : void 0;
			}
			const appendInnerControl = controlVariant.value === "split" ? createVNode("div", { "class": "v-number-input__control" }, [createVNode(VDivider, { "vertical": true }, null), incrementControlNode()]) : props.reverse || controlVariant.value === "hidden" ? void 0 : createVNode(Fragment, null, [dividerNode(), controlNode()]);
			const hasAppendInner = slots["append-inner"] || appendInnerControl;
			const prependInnerControl = controlVariant.value === "split" ? createVNode("div", { "class": "v-number-input__control" }, [decrementControlNode(), createVNode(VDivider, { "vertical": true }, null)]) : props.reverse && controlVariant.value !== "hidden" ? createVNode(Fragment, null, [controlNode(), dividerNode()]) : void 0;
			const hasPrependInner = slots["prepend-inner"] || prependInnerControl;
			return createVNode(VTextField, mergeProps({
				"ref": vTextFieldRef,
				"modelValue": inputText.value,
				"onUpdate:modelValue": ($event) => inputText.value = $event,
				"validationValue": model.value,
				"onBeforeinput": onBeforeinput,
				"onFocus": onFocus,
				"onBlur": onBlur,
				"onKeydown": onKeydown,
				"class": [
					"v-number-input",
					{
						"v-number-input--default": controlVariant.value === "default",
						"v-number-input--hide-input": props.hideInput,
						"v-number-input--inset": props.inset,
						"v-number-input--reverse": props.reverse,
						"v-number-input--split": controlVariant.value === "split",
						"v-number-input--stacked": controlVariant.value === "stacked"
					},
					props.class
				]
			}, textFieldProps, {
				"style": props.style,
				"inputmode": "decimal"
			}), {
				...slots,
				"append-inner": hasAppendInner ? function() {
					for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
					return createVNode(Fragment, null, [slots["append-inner"]?.(...args), appendInnerControl]);
				} : void 0,
				"prepend-inner": hasPrependInner ? function() {
					for (var _len2 = arguments.length, args = new Array(_len2), _key2 = 0; _key2 < _len2; _key2++) args[_key2] = arguments[_key2];
					return createVNode(Fragment, null, [prependInnerControl, slots["prepend-inner"]?.(...args)]);
				} : void 0
			});
		});
		return forwardRefs({}, vTextFieldRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VOtpInput/VOtpInput.js
var makeVOtpInputProps = propsFactory({
	autofocus: Boolean,
	divider: String,
	focusAll: Boolean,
	label: {
		type: String,
		default: "$vuetify.input.otp"
	},
	length: {
		type: [Number, String],
		default: 6
	},
	modelValue: {
		type: [Number, String],
		default: void 0
	},
	placeholder: String,
	type: {
		type: String,
		default: "number"
	},
	...makeDimensionProps(),
	...makeFocusProps(),
	...pick(makeVFieldProps({ variant: "outlined" }), [
		"baseColor",
		"bgColor",
		"class",
		"color",
		"disabled",
		"error",
		"loading",
		"rounded",
		"style",
		"theme",
		"variant"
	])
}, "VOtpInput");
var VOtpInput = genericComponent()({
	name: "VOtpInput",
	props: makeVOtpInputProps(),
	emits: {
		finish: (val) => true,
		"update:focused": (val) => true,
		"update:modelValue": (val) => true
	},
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const { dimensionStyles } = useDimension(props);
		const { isFocused, focus, blur } = useFocus(props);
		const model = useProxiedModel(props, "modelValue", "", (val) => val == null ? [] : String(val).split(""), (val) => val.join(""));
		const { t } = useLocale();
		const length = computed(() => Number(props.length));
		const fields = computed(() => Array(length.value).fill(0));
		const focusIndex = ref(-1);
		const contentRef = ref();
		const inputRef = ref([]);
		const current = computed(() => inputRef.value[focusIndex.value]);
		function onInput() {
			if (isValidNumber(current.value.value)) {
				current.value.value = "";
				return;
			}
			const array = model.value.slice();
			const value = current.value.value;
			array[focusIndex.value] = value;
			let target = null;
			if (focusIndex.value > model.value.length) target = model.value.length + 1;
			else if (focusIndex.value + 1 !== length.value) target = "next";
			model.value = array;
			if (target) focusChild(contentRef.value, target);
		}
		function onKeydown(e) {
			const array = model.value.slice();
			const index = focusIndex.value;
			let target = null;
			if (![
				"ArrowLeft",
				"ArrowRight",
				"Backspace",
				"Delete"
			].includes(e.key)) return;
			e.preventDefault();
			if (e.key === "ArrowLeft") target = "prev";
			else if (e.key === "ArrowRight") target = "next";
			else if (["Backspace", "Delete"].includes(e.key)) {
				array[focusIndex.value] = "";
				model.value = array;
				if (focusIndex.value > 0 && e.key === "Backspace") target = "prev";
				else requestAnimationFrame(() => {
					inputRef.value[index]?.select();
				});
			}
			requestAnimationFrame(() => {
				if (target != null) focusChild(contentRef.value, target);
			});
		}
		function onPaste(index, e) {
			e.preventDefault();
			e.stopPropagation();
			const clipboardText = e?.clipboardData?.getData("Text").slice(0, length.value) ?? "";
			if (isValidNumber(clipboardText)) return;
			model.value = clipboardText.split("");
			inputRef.value?.[index].blur();
		}
		function reset() {
			model.value = [];
		}
		function onFocus(e, index) {
			focus();
			focusIndex.value = index;
		}
		function onBlur() {
			blur();
			focusIndex.value = -1;
		}
		function isValidNumber(value) {
			return props.type === "number" && /[^0-9]/g.test(value);
		}
		provideDefaults({ VField: {
			color: toRef(() => props.color),
			bgColor: toRef(() => props.color),
			baseColor: toRef(() => props.baseColor),
			disabled: toRef(() => props.disabled),
			error: toRef(() => props.error),
			variant: toRef(() => props.variant)
		} }, { scoped: true });
		watch(model, (val) => {
			if (val.length === length.value) emit("finish", val.join(""));
		}, { deep: true });
		watch(focusIndex, (val) => {
			if (val < 0) return;
			nextTick(() => {
				inputRef.value[val]?.select();
			});
		});
		useRender(() => {
			const [rootAttrs, inputAttrs] = filterInputAttrs(attrs);
			return createVNode("div", mergeProps({
				"class": [
					"v-otp-input",
					{ "v-otp-input--divided": !!props.divider },
					props.class
				],
				"style": [props.style]
			}, rootAttrs), [createVNode("div", {
				"ref": contentRef,
				"class": "v-otp-input__content",
				"style": [dimensionStyles.value]
			}, [
				fields.value.map((_, i) => createVNode(Fragment, null, [props.divider && i !== 0 && createVNode("span", { "class": "v-otp-input__divider" }, [props.divider]), createVNode(VField, {
					"focused": isFocused.value && props.focusAll || focusIndex.value === i,
					"key": i
				}, {
					...slots,
					loader: void 0,
					default: () => {
						return createVNode("input", {
							"ref": (val) => inputRef.value[i] = val,
							"aria-label": t(props.label, i + 1),
							"autofocus": i === 0 && props.autofocus,
							"autocomplete": "one-time-code",
							"class": ["v-otp-input__field"],
							"disabled": props.disabled,
							"inputmode": props.type === "number" ? "numeric" : "text",
							"min": props.type === "number" ? 0 : void 0,
							"maxlength": i === 0 ? length.value : "1",
							"placeholder": props.placeholder,
							"type": props.type === "number" ? "text" : props.type,
							"value": model.value[i],
							"onInput": onInput,
							"onFocus": (e) => onFocus(e, i),
							"onBlur": onBlur,
							"onKeydown": onKeydown,
							"onPaste": (event) => onPaste(i, event)
						}, null);
					}
				})])),
				createVNode("input", mergeProps({
					"class": "v-otp-input-input",
					"type": "hidden"
				}, inputAttrs, { "value": model.value.join("") }), null),
				createVNode(VOverlay, {
					"contained": true,
					"content-class": "v-otp-input__loader",
					"model-value": !!props.loading,
					"persistent": true
				}, { default: () => [slots.loader?.() ?? createVNode(VProgressCircular, {
					"color": typeof props.loading === "boolean" ? void 0 : props.loading,
					"indeterminate": true,
					"size": "24",
					"width": "2"
				}, null)] }),
				slots.default?.()
			])]);
		});
		return {
			blur: () => {
				inputRef.value?.some((input) => input.blur());
			},
			focus: () => {
				inputRef.value?.[0].focus();
			},
			reset,
			isFocused
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VParallax/VParallax.js
function floor(val) {
	return Math.floor(Math.abs(val)) * Math.sign(val);
}
var makeVParallaxProps = propsFactory({
	scale: {
		type: [Number, String],
		default: .5
	},
	...makeComponentProps()
}, "VParallax");
var VParallax = genericComponent()({
	name: "VParallax",
	props: makeVParallaxProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { intersectionRef, isIntersecting } = useIntersectionObserver();
		const { resizeRef, contentRect } = useResizeObserver();
		const { height: displayHeight } = useDisplay();
		const root = ref();
		watchEffect(() => {
			intersectionRef.value = resizeRef.value = root.value?.$el;
		});
		let scrollParent;
		watch(isIntersecting, (val) => {
			if (val) {
				scrollParent = getScrollParent(intersectionRef.value);
				scrollParent = scrollParent === document.scrollingElement ? document : scrollParent;
				scrollParent.addEventListener("scroll", onScroll, { passive: true });
				onScroll();
			} else scrollParent.removeEventListener("scroll", onScroll);
		});
		onBeforeUnmount(() => {
			scrollParent?.removeEventListener("scroll", onScroll);
		});
		watch(displayHeight, onScroll);
		watch(() => contentRect.value?.height, onScroll);
		const scale = computed(() => {
			return 1 - clamp(Number(props.scale));
		});
		let frame = -1;
		function onScroll() {
			if (!isIntersecting.value) return;
			cancelAnimationFrame(frame);
			frame = requestAnimationFrame(() => {
				const el = (root.value?.$el).querySelector(".v-img__img");
				if (!el) return;
				const scrollHeight = scrollParent instanceof Document ? document.documentElement.clientHeight : scrollParent.clientHeight;
				const scrollPos = scrollParent instanceof Document ? window.scrollY : scrollParent.scrollTop;
				const top = intersectionRef.value.getBoundingClientRect().top + scrollPos;
				const height = contentRect.value.height;
				const translate = floor((scrollPos - (top + (height - scrollHeight) / 2)) * scale.value);
				const sizeScale = Math.max(1, (scale.value * (scrollHeight - height) + height) / height);
				el.style.setProperty("transform", `translateY(${translate}px) scale(${sizeScale})`);
			});
		}
		useRender(() => createVNode(VImg, {
			"class": [
				"v-parallax",
				{ "v-parallax--active": isIntersecting.value },
				props.class
			],
			"style": props.style,
			"ref": root,
			"cover": true,
			"onLoadstart": onScroll,
			"onLoad": onScroll
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VRadio/VRadio.js
var makeVRadioProps = propsFactory({ ...makeVSelectionControlProps({
	falseIcon: "$radioOff",
	trueIcon: "$radioOn"
}) }, "VRadio");
var VRadio = genericComponent()({
	name: "VRadio",
	props: makeVRadioProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => {
			return createVNode(VSelectionControl, mergeProps(VSelectionControl.filterProps(props), {
				"class": ["v-radio", props.class],
				"style": props.style,
				"type": "radio"
			}), slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VRadioGroup/VRadioGroup.js
var makeVRadioGroupProps = propsFactory({
	height: {
		type: [Number, String],
		default: "auto"
	},
	...makeVInputProps(),
	...omit(makeSelectionControlGroupProps(), ["multiple"]),
	trueIcon: {
		type: IconValue,
		default: "$radioOn"
	},
	falseIcon: {
		type: IconValue,
		default: "$radioOff"
	},
	type: {
		type: String,
		default: "radio"
	}
}, "VRadioGroup");
var VRadioGroup = genericComponent()({
	name: "VRadioGroup",
	inheritAttrs: false,
	props: makeVRadioGroupProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const uid = useId();
		const id = computed(() => props.id || `radio-group-${uid}`);
		const model = useProxiedModel(props, "modelValue");
		useRender(() => {
			const [rootAttrs, controlAttrs] = filterInputAttrs(attrs);
			const inputProps = VInput.filterProps(props);
			const controlProps = VSelectionControl.filterProps(props);
			const label = slots.label ? slots.label({
				label: props.label,
				props: { for: id.value }
			}) : props.label;
			return createVNode(VInput, mergeProps({
				"class": ["v-radio-group", props.class],
				"style": props.style
			}, rootAttrs, inputProps, {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"id": id.value
			}), {
				...slots,
				default: (_ref2) => {
					let { id, messagesId, isDisabled, isReadonly } = _ref2;
					return createVNode(Fragment, null, [label && createVNode(VLabel, { "id": id.value }, { default: () => [label] }), createVNode(VSelectionControlGroup, mergeProps(controlProps, {
						"id": id.value,
						"aria-describedby": messagesId.value,
						"defaultsTarget": "VRadio",
						"trueIcon": props.trueIcon,
						"falseIcon": props.falseIcon,
						"type": props.type,
						"disabled": isDisabled.value,
						"readonly": isReadonly.value,
						"aria-labelledby": label ? id.value : void 0,
						"multiple": false
					}, controlAttrs, {
						"modelValue": model.value,
						"onUpdate:modelValue": ($event) => model.value = $event
					}), slots)]);
				}
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VRangeSlider/VRangeSlider.js
var makeVRangeSliderProps = propsFactory({
	...makeFocusProps(),
	...makeVInputProps(),
	...makeSliderProps(),
	strict: Boolean,
	modelValue: {
		type: Array,
		default: () => [0, 0]
	}
}, "VRangeSlider");
var VRangeSlider = genericComponent()({
	name: "VRangeSlider",
	props: makeVRangeSliderProps(),
	emits: {
		"update:focused": (value) => true,
		"update:modelValue": (value) => true,
		end: (value) => true,
		start: (value) => true
	},
	setup(props, _ref) {
		let { slots, emit } = _ref;
		const startThumbRef = ref();
		const stopThumbRef = ref();
		const inputRef = ref();
		const { rtlClasses } = useRtl();
		function getActiveThumb(e) {
			if (!startThumbRef.value || !stopThumbRef.value) return;
			const startOffset = getOffset(e, startThumbRef.value.$el, props.direction);
			const stopOffset = getOffset(e, stopThumbRef.value.$el, props.direction);
			const a = Math.abs(startOffset);
			const b = Math.abs(stopOffset);
			return a < b || a === b && startOffset < 0 ? startThumbRef.value.$el : stopThumbRef.value.$el;
		}
		const steps = useSteps(props);
		const model = useProxiedModel(props, "modelValue", void 0, (arr) => {
			if (!arr?.length) return [0, 0];
			return arr.map((value) => steps.roundValue(value));
		});
		const { activeThumbRef, hasLabels, max, min, mousePressed, onSliderMousedown, onSliderTouchstart, position, trackContainerRef, readonly } = useSlider({
			props,
			steps,
			onSliderStart: () => {
				emit("start", model.value);
			},
			onSliderEnd: (_ref2) => {
				let { value } = _ref2;
				const newValue = activeThumbRef.value === startThumbRef.value?.$el ? [value, model.value[1]] : [model.value[0], value];
				if (!props.strict && newValue[0] < newValue[1]) model.value = newValue;
				emit("end", model.value);
			},
			onSliderMove: (_ref3) => {
				let { value } = _ref3;
				const [start, stop] = model.value;
				if (!props.strict && start === stop && start !== min.value) {
					activeThumbRef.value = value > start ? stopThumbRef.value?.$el : startThumbRef.value?.$el;
					activeThumbRef.value?.focus();
				}
				if (activeThumbRef.value === startThumbRef.value?.$el) model.value = [Math.min(value, stop), stop];
				else model.value = [start, Math.max(start, value)];
			},
			getActiveThumb
		});
		const { isFocused, focus, blur } = useFocus(props);
		const trackStart = computed(() => position(model.value[0]));
		const trackStop = computed(() => position(model.value[1]));
		useRender(() => {
			const inputProps = VInput.filterProps(props);
			const hasPrepend = !!(props.label || slots.label || slots.prepend);
			return createVNode(VInput, mergeProps({
				"class": [
					"v-slider",
					"v-range-slider",
					{
						"v-slider--has-labels": !!slots["tick-label"] || hasLabels.value,
						"v-slider--focused": isFocused.value,
						"v-slider--pressed": mousePressed.value,
						"v-slider--disabled": props.disabled
					},
					rtlClasses.value,
					props.class
				],
				"style": props.style,
				"ref": inputRef
			}, inputProps, { "focused": isFocused.value }), {
				...slots,
				prepend: hasPrepend ? (slotProps) => createVNode(Fragment, null, [slots.label?.(slotProps) ?? (props.label ? createVNode(VLabel, {
					"class": "v-slider__label",
					"text": props.label
				}, null) : void 0), slots.prepend?.(slotProps)]) : void 0,
				default: (_ref4) => {
					let { id, messagesId } = _ref4;
					return createVNode("div", {
						"class": "v-slider__container",
						"onMousedown": !readonly.value ? onSliderMousedown : void 0,
						"onTouchstartPassive": !readonly.value ? onSliderTouchstart : void 0
					}, [
						createVNode("input", {
							"id": `${id.value}_start`,
							"name": props.name || id.value,
							"disabled": !!props.disabled,
							"readonly": !!props.readonly,
							"tabindex": "-1",
							"value": model.value[0]
						}, null),
						createVNode("input", {
							"id": `${id.value}_stop`,
							"name": props.name || id.value,
							"disabled": !!props.disabled,
							"readonly": !!props.readonly,
							"tabindex": "-1",
							"value": model.value[1]
						}, null),
						createVNode(VSliderTrack, {
							"ref": trackContainerRef,
							"start": trackStart.value,
							"stop": trackStop.value
						}, { "tick-label": slots["tick-label"] }),
						createVNode(VSliderThumb, {
							"ref": startThumbRef,
							"aria-describedby": messagesId.value,
							"focused": isFocused && activeThumbRef.value === startThumbRef.value?.$el,
							"modelValue": model.value[0],
							"onUpdate:modelValue": (v) => model.value = [v, model.value[1]],
							"onFocus": (e) => {
								focus();
								activeThumbRef.value = startThumbRef.value?.$el;
								if (max.value !== min.value && model.value[0] === model.value[1] && model.value[1] === min.value && e.relatedTarget !== stopThumbRef.value?.$el) {
									startThumbRef.value?.$el.blur();
									stopThumbRef.value?.$el.focus();
								}
							},
							"onBlur": () => {
								blur();
								activeThumbRef.value = void 0;
							},
							"min": min.value,
							"max": model.value[1],
							"position": trackStart.value,
							"ripple": props.ripple
						}, { "thumb-label": slots["thumb-label"] }),
						createVNode(VSliderThumb, {
							"ref": stopThumbRef,
							"aria-describedby": messagesId.value,
							"focused": isFocused && activeThumbRef.value === stopThumbRef.value?.$el,
							"modelValue": model.value[1],
							"onUpdate:modelValue": (v) => model.value = [model.value[0], v],
							"onFocus": (e) => {
								focus();
								activeThumbRef.value = stopThumbRef.value?.$el;
								if (max.value !== min.value && model.value[0] === model.value[1] && model.value[0] === max.value && e.relatedTarget !== startThumbRef.value?.$el) {
									stopThumbRef.value?.$el.blur();
									startThumbRef.value?.$el.focus();
								}
							},
							"onBlur": () => {
								blur();
								activeThumbRef.value = void 0;
							},
							"min": model.value[0],
							"max": max.value,
							"position": trackStop.value,
							"ripple": props.ripple
						}, { "thumb-label": slots["thumb-label"] })
					]);
				}
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VRating/VRating.js
var makeVRatingProps = propsFactory({
	name: String,
	itemAriaLabel: {
		type: String,
		default: "$vuetify.rating.ariaLabel.item"
	},
	activeColor: String,
	color: String,
	clearable: Boolean,
	disabled: Boolean,
	emptyIcon: {
		type: IconValue,
		default: "$ratingEmpty"
	},
	fullIcon: {
		type: IconValue,
		default: "$ratingFull"
	},
	halfIncrements: Boolean,
	hover: Boolean,
	length: {
		type: [Number, String],
		default: 5
	},
	readonly: Boolean,
	modelValue: {
		type: [Number, String],
		default: 0
	},
	itemLabels: Array,
	itemLabelPosition: {
		type: String,
		default: "top",
		validator: (v) => ["top", "bottom"].includes(v)
	},
	ripple: Boolean,
	...makeComponentProps(),
	...makeDensityProps(),
	...makeSizeProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VRating");
var VRating = genericComponent()({
	name: "VRating",
	props: makeVRatingProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { t } = useLocale();
		const { themeClasses } = provideTheme(props);
		const rating = useProxiedModel(props, "modelValue");
		const normalizedValue = computed(() => clamp(parseFloat(rating.value), 0, Number(props.length)));
		const range = computed(() => createRange(Number(props.length), 1));
		const increments = computed(() => range.value.flatMap((v) => props.halfIncrements ? [v - .5, v] : [v]));
		const hoverIndex = shallowRef(-1);
		const itemState = computed(() => increments.value.map((value) => {
			const isHovering = props.hover && hoverIndex.value > -1;
			const isFilled = normalizedValue.value >= value;
			const isHovered = hoverIndex.value >= value;
			const icon = (isHovering ? isHovered : isFilled) ? props.fullIcon : props.emptyIcon;
			const activeColor = props.activeColor ?? props.color;
			return {
				isFilled,
				isHovered,
				icon,
				color: isFilled || isHovered ? activeColor : props.color
			};
		}));
		const eventState = computed(() => [0, ...increments.value].map((value) => {
			function onMouseenter() {
				hoverIndex.value = value;
			}
			function onMouseleave() {
				hoverIndex.value = -1;
			}
			function onClick() {
				if (props.disabled || props.readonly) return;
				rating.value = normalizedValue.value === value && props.clearable ? 0 : value;
			}
			return {
				onMouseenter: props.hover ? onMouseenter : void 0,
				onMouseleave: props.hover ? onMouseleave : void 0,
				onClick
			};
		}));
		const uid = useId();
		const name = computed(() => props.name ?? `v-rating-${uid}`);
		function VRatingItem(_ref2) {
			let { value, index, showStar = true } = _ref2;
			const { onMouseenter, onMouseleave, onClick } = eventState.value[index + 1];
			const id = `${name.value}-${String(value).replace(".", "-")}`;
			const btnProps = {
				color: itemState.value[index]?.color,
				density: props.density,
				disabled: props.disabled,
				icon: itemState.value[index]?.icon,
				ripple: props.ripple,
				size: props.size,
				variant: "plain"
			};
			return createVNode(Fragment, null, [createVNode("label", {
				"for": id,
				"class": {
					"v-rating__item--half": props.halfIncrements && value % 1 > 0,
					"v-rating__item--full": props.halfIncrements && value % 1 === 0
				},
				"onMouseenter": onMouseenter,
				"onMouseleave": onMouseleave,
				"onClick": onClick
			}, [createVNode("span", { "class": "v-rating__hidden" }, [t(props.itemAriaLabel, value, props.length)]), !showStar ? void 0 : slots.item ? slots.item({
				...itemState.value[index],
				props: btnProps,
				value,
				index,
				rating: normalizedValue.value
			}) : createVNode(VBtn, mergeProps({ "aria-label": t(props.itemAriaLabel, value, props.length) }, btnProps), null)]), createVNode("input", {
				"class": "v-rating__hidden",
				"name": name.value,
				"id": id,
				"type": "radio",
				"value": value,
				"checked": normalizedValue.value === value,
				"tabindex": -1,
				"readonly": props.readonly,
				"disabled": props.disabled
			}, null)]);
		}
		function createLabel(labelProps) {
			if (slots["item-label"]) return slots["item-label"](labelProps);
			if (labelProps.label) return createVNode("span", null, [labelProps.label]);
			return createVNode("span", null, [createTextVNode("\xA0")]);
		}
		useRender(() => {
			const hasLabels = !!props.itemLabels?.length || slots["item-label"];
			return createVNode(props.tag, {
				"class": [
					"v-rating",
					{
						"v-rating--hover": props.hover,
						"v-rating--readonly": props.readonly
					},
					themeClasses.value,
					props.class
				],
				"style": props.style
			}, { default: () => [createVNode(VRatingItem, {
				"value": 0,
				"index": -1,
				"showStar": false
			}, null), range.value.map((value, i) => createVNode("div", { "class": "v-rating__wrapper" }, [
				hasLabels && props.itemLabelPosition === "top" ? createLabel({
					value,
					index: i,
					label: props.itemLabels?.[i]
				}) : void 0,
				createVNode("div", { "class": "v-rating__item" }, [props.halfIncrements ? createVNode(Fragment, null, [createVNode(VRatingItem, {
					"value": value - .5,
					"index": i * 2
				}, null), createVNode(VRatingItem, {
					"value": value,
					"index": i * 2 + 1
				}, null)]) : createVNode(VRatingItem, {
					"value": value,
					"index": i
				}, null)]),
				hasLabels && props.itemLabelPosition === "bottom" ? createLabel({
					value,
					index: i,
					label: props.itemLabels?.[i]
				}) : void 0
			]))] });
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSkeletonLoader/VSkeletonLoader.js
var rootTypes = {
	actions: "button@2",
	article: "heading, paragraph",
	avatar: "avatar",
	button: "button",
	card: "image, heading",
	"card-avatar": "image, list-item-avatar",
	chip: "chip",
	"date-picker": "list-item, heading, divider, date-picker-options, date-picker-days, actions",
	"date-picker-options": "text, avatar@2",
	"date-picker-days": "avatar@28",
	divider: "divider",
	heading: "heading",
	image: "image",
	"list-item": "text",
	"list-item-avatar": "avatar, text",
	"list-item-two-line": "sentences",
	"list-item-avatar-two-line": "avatar, sentences",
	"list-item-three-line": "paragraph",
	"list-item-avatar-three-line": "avatar, paragraph",
	ossein: "ossein",
	paragraph: "text@3",
	sentences: "text@2",
	subtitle: "text",
	table: "table-heading, table-thead, table-tbody, table-tfoot",
	"table-heading": "chip, text",
	"table-thead": "heading@6",
	"table-tbody": "table-row-divider@6",
	"table-row-divider": "table-row, divider",
	"table-row": "text@6",
	"table-tfoot": "text@2, avatar@2",
	text: "text"
};
function genBone(type) {
	let children = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : [];
	return createVNode("div", { "class": ["v-skeleton-loader__bone", `v-skeleton-loader__${type}`] }, [children]);
}
function genBones(bone) {
	const [type, length] = bone.split("@");
	return Array.from({ length }).map(() => genStructure(type));
}
function genStructure(type) {
	let children = [];
	if (!type) return children;
	const bone = rootTypes[type];
	if (type === bone) {} else if (type.includes(",")) return mapBones(type);
	else if (type.includes("@")) return genBones(type);
	else if (bone.includes(",")) children = mapBones(bone);
	else if (bone.includes("@")) children = genBones(bone);
	else if (bone) children.push(genStructure(bone));
	return [genBone(type, children)];
}
function mapBones(bones) {
	return bones.replace(/\s/g, "").split(",").map(genStructure);
}
var makeVSkeletonLoaderProps = propsFactory({
	boilerplate: Boolean,
	color: String,
	loading: Boolean,
	loadingText: {
		type: String,
		default: "$vuetify.loading"
	},
	type: {
		type: [String, Array],
		default: "ossein"
	},
	...makeDimensionProps(),
	...makeElevationProps(),
	...makeThemeProps()
}, "VSkeletonLoader");
var VSkeletonLoader = genericComponent()({
	name: "VSkeletonLoader",
	props: makeVSkeletonLoaderProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { dimensionStyles } = useDimension(props);
		const { elevationClasses } = useElevation(props);
		const { themeClasses } = provideTheme(props);
		const { t } = useLocale();
		const items = computed(() => genStructure(wrapInArray(props.type).join(",")));
		useRender(() => {
			const isLoading = !slots.default || props.loading;
			const loadingProps = props.boilerplate || !isLoading ? {} : {
				ariaLive: "polite",
				ariaLabel: t(props.loadingText),
				role: "alert"
			};
			return createVNode("div", mergeProps({
				"class": [
					"v-skeleton-loader",
					{ "v-skeleton-loader--boilerplate": props.boilerplate },
					themeClasses.value,
					backgroundColorClasses.value,
					elevationClasses.value
				],
				"style": [backgroundColorStyles.value, isLoading ? dimensionStyles.value : {}]
			}, loadingProps), [isLoading ? items.value : slots.default?.()]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSlideGroup/VSlideGroupItem.js
var VSlideGroupItem = genericComponent()({
	name: "VSlideGroupItem",
	props: makeGroupItemProps(),
	emits: { "group:selected": (val) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const slideGroupItem = useGroupItem(props, VSlideGroupSymbol);
		return () => slots.default?.({
			isSelected: slideGroupItem.isSelected.value,
			select: slideGroupItem.select,
			toggle: slideGroupItem.toggle,
			selectedClass: slideGroupItem.selectedClass.value
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSnackbar/VSnackbar.js
function useCountdown(milliseconds) {
	const time = shallowRef(milliseconds());
	let timer = -1;
	function clear() {
		clearInterval(timer);
	}
	function reset() {
		clear();
		nextTick(() => time.value = milliseconds());
	}
	function start(el) {
		const style = el ? getComputedStyle(el) : { transitionDuration: .2 };
		const interval = parseFloat(style.transitionDuration) * 1e3 || 200;
		clear();
		if (time.value <= 0) return;
		const startTime = performance.now();
		timer = window.setInterval(() => {
			const elapsed = performance.now() - startTime + interval;
			time.value = Math.max(milliseconds() - elapsed, 0);
			if (time.value <= 0) clear();
		}, interval);
	}
	onScopeDispose(clear);
	return {
		clear,
		time,
		start,
		reset
	};
}
var makeVSnackbarProps = propsFactory({
	multiLine: Boolean,
	text: String,
	timer: [Boolean, String],
	timeout: {
		type: [Number, String],
		default: 5e3
	},
	vertical: Boolean,
	...makeLocationProps({ location: "bottom" }),
	...makePositionProps(),
	...makeRoundedProps(),
	...makeVariantProps(),
	...makeThemeProps(),
	...omit(makeVOverlayProps({ transition: "v-snackbar-transition" }), [
		"persistent",
		"noClickAnimation",
		"scrim",
		"scrollStrategy"
	])
}, "VSnackbar");
var VSnackbar = genericComponent()({
	name: "VSnackbar",
	props: makeVSnackbarProps(),
	emits: { "update:modelValue": (v) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const isActive = useProxiedModel(props, "modelValue");
		const { positionClasses } = usePosition(props);
		const { scopeId } = useScopeId();
		const { themeClasses } = provideTheme(props);
		const { colorClasses, colorStyles, variantClasses } = useVariant(props);
		const { roundedClasses } = useRounded(props);
		const countdown = useCountdown(() => Number(props.timeout));
		const overlay = ref();
		const timerRef = ref();
		const isHovering = shallowRef(false);
		const startY = shallowRef(0);
		const mainStyles = ref();
		const hasLayout = inject(VuetifyLayoutKey, void 0);
		useToggleScope(() => !!hasLayout, () => {
			const layout = useLayout();
			watchEffect(() => {
				mainStyles.value = layout.mainStyles.value;
			});
		});
		watch(isActive, startTimeout);
		watch(() => props.timeout, startTimeout);
		onMounted(() => {
			if (isActive.value) startTimeout();
		});
		let activeTimeout = -1;
		function startTimeout() {
			countdown.reset();
			window.clearTimeout(activeTimeout);
			const timeout = Number(props.timeout);
			if (!isActive.value || timeout === -1) return;
			const element = refElement(timerRef.value);
			countdown.start(element);
			activeTimeout = window.setTimeout(() => {
				isActive.value = false;
			}, timeout);
		}
		function clearTimeout() {
			countdown.reset();
			window.clearTimeout(activeTimeout);
		}
		function onPointerenter() {
			isHovering.value = true;
			clearTimeout();
		}
		function onPointerleave() {
			isHovering.value = false;
			startTimeout();
		}
		function onTouchstart(event) {
			startY.value = event.touches[0].clientY;
		}
		function onTouchend(event) {
			if (Math.abs(startY.value - event.changedTouches[0].clientY) > 50) isActive.value = false;
		}
		function onAfterLeave() {
			if (isHovering.value) onPointerleave();
		}
		const locationClasses = computed(() => {
			return props.location.split(" ").reduce((acc, loc) => {
				acc[`v-snackbar--${loc}`] = true;
				return acc;
			}, {});
		});
		useRender(() => {
			const overlayProps = VOverlay.filterProps(props);
			const hasContent = !!(slots.default || slots.text || props.text);
			return createVNode(VOverlay, mergeProps({
				"ref": overlay,
				"class": [
					"v-snackbar",
					{
						"v-snackbar--active": isActive.value,
						"v-snackbar--multi-line": props.multiLine && !props.vertical,
						"v-snackbar--timer": !!props.timer,
						"v-snackbar--vertical": props.vertical
					},
					locationClasses.value,
					positionClasses.value,
					props.class
				],
				"style": [mainStyles.value, props.style]
			}, overlayProps, {
				"modelValue": isActive.value,
				"onUpdate:modelValue": ($event) => isActive.value = $event,
				"contentProps": mergeProps({
					class: [
						"v-snackbar__wrapper",
						themeClasses.value,
						colorClasses.value,
						roundedClasses.value,
						variantClasses.value
					],
					style: [colorStyles.value],
					onPointerenter,
					onPointerleave
				}, overlayProps.contentProps),
				"persistent": true,
				"noClickAnimation": true,
				"scrim": false,
				"scrollStrategy": "none",
				"_disableGlobalStack": true,
				"onTouchstartPassive": onTouchstart,
				"onTouchend": onTouchend,
				"onAfterLeave": onAfterLeave
			}, scopeId), {
				default: () => [
					genOverlays(false, "v-snackbar"),
					props.timer && !isHovering.value && createVNode("div", {
						"key": "timer",
						"class": "v-snackbar__timer"
					}, [createVNode(VProgressLinear, {
						"ref": timerRef,
						"color": typeof props.timer === "string" ? props.timer : "info",
						"max": props.timeout,
						"model-value": countdown.time.value
					}, null)]),
					hasContent && createVNode("div", {
						"key": "content",
						"class": "v-snackbar__content",
						"role": "status",
						"aria-live": "polite"
					}, [slots.text?.() ?? props.text, slots.default?.()]),
					slots.actions && createVNode(VDefaultsProvider, { "defaults": { VBtn: {
						variant: "text",
						ripple: false,
						slim: true
					} } }, { default: () => [createVNode("div", { "class": "v-snackbar__actions" }, [slots.actions({ isActive })])] })
				],
				activator: slots.activator
			});
		});
		return forwardRefs({}, overlay);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSnackbarQueue/VSnackbarQueue.js
var makeVSnackbarQueueProps = propsFactory({
	closable: [Boolean, String],
	closeText: {
		type: String,
		default: "$vuetify.dismiss"
	},
	modelValue: {
		type: Array,
		default: () => []
	},
	...omit(makeVSnackbarProps(), ["modelValue"])
}, "VSnackbarQueue");
var VSnackbarQueue = genericComponent()({
	name: "VSnackbarQueue",
	props: makeVSnackbarQueueProps(),
	emits: { "update:modelValue": (val) => true },
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const { t } = useLocale();
		const isActive = shallowRef(false);
		const isVisible = shallowRef(false);
		const current = shallowRef();
		watch(() => props.modelValue.length, (val, oldVal) => {
			if (!isVisible.value && val > oldVal) showNext();
		});
		watch(isActive, (val) => {
			if (val) isVisible.value = true;
		});
		function onAfterLeave() {
			if (props.modelValue.length) showNext();
			else {
				current.value = void 0;
				isVisible.value = false;
			}
		}
		function showNext() {
			const [next, ...rest] = props.modelValue;
			emit("update:modelValue", rest);
			current.value = typeof next === "string" ? { text: next } : next;
			nextTick(() => {
				isActive.value = true;
			});
		}
		function onClickClose() {
			isActive.value = false;
		}
		const btnProps = computed(() => ({
			color: typeof props.closable === "string" ? props.closable : void 0,
			text: t(props.closeText)
		}));
		useRender(() => {
			const hasActions = !!(props.closable || slots.actions);
			const { modelValue: _, ...snackbarProps } = VSnackbar.filterProps(props);
			return createVNode(Fragment, null, [isVisible.value && !!current.value && (slots.default ? createVNode(VDefaultsProvider, { "defaults": { VSnackbar: current.value } }, { default: () => [slots.default({ item: current.value })] }) : createVNode(VSnackbar, mergeProps(snackbarProps, current.value, {
				"modelValue": isActive.value,
				"onUpdate:modelValue": ($event) => isActive.value = $event,
				"onAfterLeave": onAfterLeave
			}), {
				text: slots.text ? () => slots.text?.({ item: current.value }) : void 0,
				actions: hasActions ? () => createVNode(Fragment, null, [!slots.actions ? createVNode(VBtn, mergeProps(btnProps.value, { "onClick": onClickClose }), null) : createVNode(VDefaultsProvider, { "defaults": { VBtn: btnProps.value } }, { default: () => [slots.actions({
					item: current.value,
					props: { onClick: onClickClose }
				})] })]) : void 0
			}))]);
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSparkline/util/line.js
var makeLineProps = propsFactory({
	autoDraw: Boolean,
	autoDrawDuration: [Number, String],
	autoDrawEasing: {
		type: String,
		default: "ease"
	},
	color: String,
	gradient: {
		type: Array,
		default: () => []
	},
	gradientDirection: {
		type: String,
		validator: (val) => [
			"top",
			"bottom",
			"left",
			"right"
		].includes(val),
		default: "top"
	},
	height: {
		type: [String, Number],
		default: 75
	},
	labels: {
		type: Array,
		default: () => []
	},
	labelSize: {
		type: [Number, String],
		default: 7
	},
	lineWidth: {
		type: [String, Number],
		default: 4
	},
	id: String,
	itemValue: {
		type: String,
		default: "value"
	},
	modelValue: {
		type: Array,
		default: () => []
	},
	min: [String, Number],
	max: [String, Number],
	padding: {
		type: [String, Number],
		default: 8
	},
	showLabels: Boolean,
	smooth: [
		Boolean,
		String,
		Number
	],
	width: {
		type: [Number, String],
		default: 300
	}
}, "Line");
//#endregion
//#region node_modules/vuetify/lib/components/VSparkline/VBarline.js
var makeVBarlineProps = propsFactory({
	autoLineWidth: Boolean,
	...makeLineProps()
}, "VBarline");
var VBarline = genericComponent()({
	name: "VBarline",
	props: makeVBarlineProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const uid = useId();
		const id = computed(() => props.id || `barline-${uid}`);
		const autoDrawDuration = computed(() => Number(props.autoDrawDuration) || 500);
		const hasLabels = computed(() => {
			return Boolean(props.showLabels || props.labels.length > 0 || !!slots?.label);
		});
		const lineWidth = computed(() => parseFloat(props.lineWidth) || 4);
		const totalWidth = computed(() => Math.max(props.modelValue.length * lineWidth.value, Number(props.width)));
		const boundary = computed(() => {
			return {
				minX: 0,
				maxX: totalWidth.value,
				minY: 0,
				maxY: parseInt(props.height, 10)
			};
		});
		const items = computed(() => props.modelValue.map((item) => getPropertyFromItem(item, props.itemValue, item)));
		function genBars(values, boundary) {
			const { minX, maxX, minY, maxY } = boundary;
			const totalValues = values.length;
			let maxValue = props.max != null ? Number(props.max) : Math.max(...values);
			let minValue = props.min != null ? Number(props.min) : Math.min(...values);
			if (minValue > 0 && props.min == null) minValue = 0;
			if (maxValue < 0 && props.max == null) maxValue = 0;
			const gridX = maxX / totalValues;
			const gridY = (maxY - minY) / (maxValue - minValue || 1);
			const horizonY = maxY - Math.abs(minValue * gridY);
			return values.map((value, index) => {
				const height = Math.abs(gridY * value);
				return {
					x: minX + index * gridX,
					y: horizonY - height + Number(value < 0) * height,
					height,
					value
				};
			});
		}
		const parsedLabels = computed(() => {
			const labels = [];
			const points = genBars(items.value, boundary.value);
			const len = points.length;
			for (let i = 0; labels.length < len; i++) {
				const item = points[i];
				let value = props.labels[i];
				if (!value) value = typeof item === "object" ? item.value : item;
				labels.push({
					x: item.x,
					value: String(value)
				});
			}
			return labels;
		});
		const bars = computed(() => genBars(items.value, boundary.value));
		const offsetX = computed(() => (Math.abs(bars.value[0].x - bars.value[1].x) - lineWidth.value) / 2);
		const smooth = computed(() => typeof props.smooth === "boolean" ? props.smooth ? 2 : 0 : Number(props.smooth));
		useRender(() => {
			const gradientData = !props.gradient.slice().length ? [""] : props.gradient.slice().reverse();
			return createVNode("svg", { "display": "block" }, [
				createVNode("defs", null, [createVNode("linearGradient", {
					"id": id.value,
					"gradientUnits": "userSpaceOnUse",
					"x1": props.gradientDirection === "left" ? "100%" : "0",
					"y1": props.gradientDirection === "top" ? "100%" : "0",
					"x2": props.gradientDirection === "right" ? "100%" : "0",
					"y2": props.gradientDirection === "bottom" ? "100%" : "0"
				}, [gradientData.map((color, index) => createVNode("stop", {
					"offset": index / Math.max(gradientData.length - 1, 1),
					"stop-color": color || "currentColor"
				}, null))])]),
				createVNode("clipPath", { "id": `${id.value}-clip` }, [bars.value.map((item) => createVNode("rect", {
					"x": item.x + offsetX.value,
					"y": item.y,
					"width": lineWidth.value,
					"height": item.height,
					"rx": smooth.value,
					"ry": smooth.value
				}, [props.autoDraw && createVNode(Fragment, null, [createVNode("animate", {
					"attributeName": "y",
					"from": item.y + item.height,
					"to": item.y,
					"dur": `${autoDrawDuration.value}ms`,
					"fill": "freeze"
				}, null), createVNode("animate", {
					"attributeName": "height",
					"from": "0",
					"to": item.height,
					"dur": `${autoDrawDuration.value}ms`,
					"fill": "freeze"
				}, null)])]))]),
				hasLabels.value && createVNode("g", {
					"key": "labels",
					"style": {
						textAnchor: "middle",
						dominantBaseline: "mathematical",
						fill: "currentColor"
					}
				}, [parsedLabels.value.map((item, i) => createVNode("text", {
					"x": item.x + offsetX.value + lineWidth.value / 2,
					"y": parseInt(props.height, 10) - 2 + (parseInt(props.labelSize, 10) || 7 * .75),
					"font-size": Number(props.labelSize) || 7
				}, [slots.label?.({
					index: i,
					value: item.value
				}) ?? item.value]))]),
				createVNode("g", {
					"clip-path": `url(#${id.value}-clip)`,
					"fill": `url(#${id.value})`
				}, [createVNode("rect", {
					"x": 0,
					"y": 0,
					"width": Math.max(props.modelValue.length * lineWidth.value, Number(props.width)),
					"height": props.height
				}, null)])
			]);
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSparkline/util/path.js
/**
* From https://github.com/unsplash/react-trend/blob/master/src/helpers/DOM.helpers.js#L18
*/
function genPath(points, radius) {
	let fill = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : false;
	let height = arguments.length > 3 && arguments[3] !== void 0 ? arguments[3] : 75;
	if (points.length === 0) return "";
	const start = points.shift();
	const end = points[points.length - 1];
	return (fill ? `M${start.x} ${height - start.x + 2} L${start.x} ${start.y}` : `M${start.x} ${start.y}`) + points.map((point, index) => {
		const next = points[index + 1];
		const prev = points[index - 1] || start;
		const isCollinear = next && checkCollinear(next, point, prev);
		if (!next || isCollinear) return `L${point.x} ${point.y}`;
		const threshold = Math.min(getDistance(prev, point), getDistance(next, point));
		const radiusForPoint = threshold / 2 < radius ? threshold / 2 : radius;
		const before = moveTo(prev, point, radiusForPoint);
		const after = moveTo(next, point, radiusForPoint);
		return `L${before.x} ${before.y}S${point.x} ${point.y} ${after.x} ${after.y}`;
	}).join("") + (fill ? `L${end.x} ${height - start.x + 2} Z` : "");
}
function int(value) {
	return parseInt(value, 10);
}
/**
* https://en.wikipedia.org/wiki/Collinearity
* x=(x1+x2)/2
* y=(y1+y2)/2
*/
function checkCollinear(p0, p1, p2) {
	return int(p0.x + p2.x) === int(2 * p1.x) && int(p0.y + p2.y) === int(2 * p1.y);
}
function getDistance(p1, p2) {
	return Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
}
function moveTo(to, from, radius) {
	const vector = {
		x: to.x - from.x,
		y: to.y - from.y
	};
	const length = Math.sqrt(vector.x * vector.x + vector.y * vector.y);
	const unitVector = {
		x: vector.x / length,
		y: vector.y / length
	};
	return {
		x: from.x + unitVector.x * radius,
		y: from.y + unitVector.y * radius
	};
}
//#endregion
//#region node_modules/vuetify/lib/components/VSparkline/VTrendline.js
var makeVTrendlineProps = propsFactory({
	fill: Boolean,
	...makeLineProps()
}, "VTrendline");
var VTrendline = genericComponent()({
	name: "VTrendline",
	props: makeVTrendlineProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const uid = useId();
		const id = computed(() => props.id || `trendline-${uid}`);
		const autoDrawDuration = computed(() => Number(props.autoDrawDuration) || (props.fill ? 500 : 2e3));
		const lastLength = ref(0);
		const path = ref(null);
		function genPoints(values, boundary) {
			const { minX, maxX, minY, maxY } = boundary;
			const totalValues = values.length;
			const maxValue = props.max != null ? Number(props.max) : Math.max(...values);
			const minValue = props.min != null ? Number(props.min) : Math.min(...values);
			const gridX = (maxX - minX) / (totalValues - 1);
			const gridY = (maxY - minY) / (maxValue - minValue || 1);
			return values.map((value, index) => {
				return {
					x: minX + index * gridX,
					y: maxY - (value - minValue) * gridY,
					value
				};
			});
		}
		const hasLabels = computed(() => {
			return Boolean(props.showLabels || props.labels.length > 0 || !!slots?.label);
		});
		const lineWidth = computed(() => {
			return parseFloat(props.lineWidth) || 4;
		});
		const totalWidth = computed(() => Number(props.width));
		const boundary = computed(() => {
			const padding = Number(props.padding);
			return {
				minX: padding,
				maxX: totalWidth.value - padding,
				minY: padding,
				maxY: parseInt(props.height, 10) - padding
			};
		});
		const items = computed(() => props.modelValue.map((item) => getPropertyFromItem(item, props.itemValue, item)));
		const parsedLabels = computed(() => {
			const labels = [];
			const points = genPoints(items.value, boundary.value);
			const len = points.length;
			for (let i = 0; labels.length < len; i++) {
				const item = points[i];
				let value = props.labels[i];
				if (!value) value = typeof item === "object" ? item.value : item;
				labels.push({
					x: item.x,
					value: String(value)
				});
			}
			return labels;
		});
		watch(() => props.modelValue, async () => {
			await nextTick();
			if (!props.autoDraw || !path.value) return;
			const pathRef = path.value;
			const length = pathRef.getTotalLength();
			if (!props.fill) {
				pathRef.style.strokeDasharray = `${length}`;
				pathRef.style.strokeDashoffset = `${length}`;
				pathRef.getBoundingClientRect();
				pathRef.style.transition = `stroke-dashoffset ${autoDrawDuration.value}ms ${props.autoDrawEasing}`;
				pathRef.style.strokeDashoffset = "0";
			} else {
				pathRef.style.transformOrigin = "bottom center";
				pathRef.style.transition = "none";
				pathRef.style.transform = `scaleY(0)`;
				pathRef.getBoundingClientRect();
				pathRef.style.transition = `transform ${autoDrawDuration.value}ms ${props.autoDrawEasing}`;
				pathRef.style.transform = `scaleY(1)`;
			}
			lastLength.value = length;
		}, { immediate: true });
		function genPath$1(fill) {
			const smoothValue = typeof props.smooth === "boolean" ? props.smooth ? 8 : 0 : Number(props.smooth);
			return genPath(genPoints(items.value, boundary.value), smoothValue, fill, parseInt(props.height, 10));
		}
		useRender(() => {
			const gradientData = !props.gradient.slice().length ? [""] : props.gradient.slice().reverse();
			return createVNode("svg", {
				"display": "block",
				"stroke-width": parseFloat(props.lineWidth) ?? 4
			}, [
				createVNode("defs", null, [createVNode("linearGradient", {
					"id": id.value,
					"gradientUnits": "userSpaceOnUse",
					"x1": props.gradientDirection === "left" ? "100%" : "0",
					"y1": props.gradientDirection === "top" ? "100%" : "0",
					"x2": props.gradientDirection === "right" ? "100%" : "0",
					"y2": props.gradientDirection === "bottom" ? "100%" : "0"
				}, [gradientData.map((color, index) => createVNode("stop", {
					"offset": index / Math.max(gradientData.length - 1, 1),
					"stop-color": color || "currentColor"
				}, null))])]),
				hasLabels.value && createVNode("g", {
					"key": "labels",
					"style": {
						textAnchor: "middle",
						dominantBaseline: "mathematical",
						fill: "currentColor"
					}
				}, [parsedLabels.value.map((item, i) => createVNode("text", {
					"x": item.x + lineWidth.value / 2 + lineWidth.value / 2,
					"y": parseInt(props.height, 10) - 4 + (parseInt(props.labelSize, 10) || 7 * .75),
					"font-size": Number(props.labelSize) || 7
				}, [slots.label?.({
					index: i,
					value: item.value
				}) ?? item.value]))]),
				createVNode("path", {
					"ref": path,
					"d": genPath$1(props.fill),
					"fill": props.fill ? `url(#${id.value})` : "none",
					"stroke": props.fill ? "none" : `url(#${id.value})`
				}, null),
				props.fill && createVNode("path", {
					"d": genPath$1(false),
					"fill": "none",
					"stroke": props.color ?? props.gradient?.[0]
				}, null)
			]);
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSparkline/VSparkline.js
var makeVSparklineProps = propsFactory({
	type: {
		type: String,
		default: "trend"
	},
	...makeVBarlineProps(),
	...makeVTrendlineProps()
}, "VSparkline");
var VSparkline = genericComponent()({
	name: "VSparkline",
	props: makeVSparklineProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { textColorClasses, textColorStyles } = useTextColor(() => props.color);
		const hasLabels = computed(() => {
			return Boolean(props.showLabels || props.labels.length > 0 || !!slots?.label);
		});
		const totalHeight = computed(() => {
			let height = parseInt(props.height, 10);
			if (hasLabels.value) height += parseInt(props.labelSize, 10) * 1.5;
			return height;
		});
		useRender(() => {
			const Tag = props.type === "trend" ? VTrendline : VBarline;
			const lineProps = props.type === "trend" ? VTrendline.filterProps(props) : VBarline.filterProps(props);
			return createVNode(Tag, mergeProps({
				"key": props.type,
				"class": textColorClasses.value,
				"style": textColorStyles.value,
				"viewBox": `0 0 ${props.width} ${parseInt(totalHeight.value, 10)}`
			}, lineProps), slots);
		});
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSpeedDial/VSpeedDial.js
var makeVSpeedDialProps = propsFactory({
	...makeComponentProps(),
	...makeVMenuProps({
		offset: 8,
		minWidth: 0,
		openDelay: 0,
		closeDelay: 100,
		location: "top center",
		transition: "scale-transition"
	})
}, "VSpeedDial");
var VSpeedDial = genericComponent()({
	name: "VSpeedDial",
	props: makeVSpeedDialProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const menuRef = ref();
		const location = computed(() => {
			const [y, x = "center"] = props.location?.split(" ") ?? [];
			return `${y} ${x}`;
		});
		const locationClasses = computed(() => ({ [`v-speed-dial__content--${location.value.replace(" ", "-")}`]: true }));
		useRender(() => {
			return createVNode(VMenu, mergeProps(VMenu.filterProps(props), {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"class": props.class,
				"style": props.style,
				"contentClass": [
					"v-speed-dial__content",
					locationClasses.value,
					props.contentClass
				],
				"location": location.value,
				"ref": menuRef,
				"transition": "fade-transition"
			}), {
				...slots,
				default: (slotProps) => createVNode(VDefaultsProvider, { "defaults": { VBtn: { size: "small" } } }, { default: () => [createVNode(MaybeTransition, {
					"appear": true,
					"group": true,
					"transition": props.transition
				}, { default: () => [slots.default?.(slotProps)] })] })
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VStepper/shared.js
var VStepperSymbol = Symbol.for("vuetify:v-stepper");
//#endregion
//#region node_modules/vuetify/lib/components/VStepper/VStepperActions.js
var makeVStepperActionsProps = propsFactory({
	color: String,
	disabled: {
		type: [Boolean, String],
		default: false
	},
	prevText: {
		type: String,
		default: "$vuetify.stepper.prev"
	},
	nextText: {
		type: String,
		default: "$vuetify.stepper.next"
	}
}, "VStepperActions");
var VStepperActions = genericComponent()({
	name: "VStepperActions",
	props: makeVStepperActionsProps(),
	emits: {
		"click:prev": () => true,
		"click:next": () => true
	},
	setup(props, _ref) {
		let { emit, slots } = _ref;
		const { t } = useLocale();
		function onClickPrev() {
			emit("click:prev");
		}
		function onClickNext() {
			emit("click:next");
		}
		useRender(() => {
			const prevSlotProps = { onClick: onClickPrev };
			const nextSlotProps = { onClick: onClickNext };
			return createVNode("div", { "class": "v-stepper-actions" }, [createVNode(VDefaultsProvider, { "defaults": { VBtn: {
				disabled: ["prev", true].includes(props.disabled),
				text: t(props.prevText),
				variant: "text"
			} } }, { default: () => [slots.prev?.({ props: prevSlotProps }) ?? createVNode(VBtn, prevSlotProps, null)] }), createVNode(VDefaultsProvider, { "defaults": { VBtn: {
				color: props.color,
				disabled: ["next", true].includes(props.disabled),
				text: t(props.nextText),
				variant: "tonal"
			} } }, { default: () => [slots.next?.({ props: nextSlotProps }) ?? createVNode(VBtn, nextSlotProps, null)] })]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VStepper/VStepperHeader.js
var VStepperHeader = createSimpleFunctional("v-stepper-header");
var makeVStepperItemProps = propsFactory({
	...propsFactory({
		color: String,
		title: String,
		subtitle: String,
		complete: Boolean,
		completeIcon: {
			type: IconValue,
			default: "$complete"
		},
		editable: Boolean,
		editIcon: {
			type: IconValue,
			default: "$edit"
		},
		error: Boolean,
		errorIcon: {
			type: IconValue,
			default: "$error"
		},
		icon: IconValue,
		ripple: {
			type: [Boolean, Object],
			default: true
		},
		rules: {
			type: Array,
			default: () => []
		}
	}, "StepperItem")(),
	...makeGroupItemProps()
}, "VStepperItem");
var VStepperItem = genericComponent()({
	name: "VStepperItem",
	directives: { Ripple },
	props: makeVStepperItemProps(),
	emits: { "group:selected": (val) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const group = useGroupItem(props, VStepperSymbol, true);
		const step = computed(() => group?.value.value ?? props.value);
		const isValid = computed(() => props.rules.every((handler) => handler() === true));
		const isClickable = computed(() => !props.disabled && props.editable);
		const canEdit = computed(() => !props.disabled && props.editable);
		const hasError = computed(() => props.error || !isValid.value);
		const hasCompleted = computed(() => props.complete || props.rules.length > 0 && isValid.value);
		const icon = computed(() => {
			if (hasError.value) return props.errorIcon;
			if (hasCompleted.value) return props.completeIcon;
			if (group.isSelected.value && props.editable) return props.editIcon;
			return props.icon;
		});
		const slotProps = computed(() => ({
			canEdit: canEdit.value,
			hasError: hasError.value,
			hasCompleted: hasCompleted.value,
			title: props.title,
			subtitle: props.subtitle,
			step: step.value,
			value: props.value
		}));
		useRender(() => {
			const hasColor = (!group || group.isSelected.value || hasCompleted.value || canEdit.value) && !hasError.value && !props.disabled;
			const hasTitle = !!(props.title != null || slots.title);
			const hasSubtitle = !!(props.subtitle != null || slots.subtitle);
			function onClick() {
				group?.toggle();
			}
			return withDirectives(createVNode("button", {
				"class": [
					"v-stepper-item",
					{
						"v-stepper-item--complete": hasCompleted.value,
						"v-stepper-item--disabled": props.disabled,
						"v-stepper-item--error": hasError.value
					},
					group?.selectedClass.value
				],
				"disabled": !props.editable,
				"type": "button",
				"onClick": onClick
			}, [
				isClickable.value && genOverlays(true, "v-stepper-item"),
				createVNode(VAvatar, {
					"key": "stepper-avatar",
					"class": "v-stepper-item__avatar",
					"color": hasColor ? props.color : void 0,
					"size": 24
				}, { default: () => [slots.icon?.(slotProps.value) ?? (icon.value ? createVNode(VIcon, { "icon": icon.value }, null) : step.value)] }),
				createVNode("div", { "class": "v-stepper-item__content" }, [
					hasTitle && createVNode("div", {
						"key": "title",
						"class": "v-stepper-item__title"
					}, [slots.title?.(slotProps.value) ?? props.title]),
					hasSubtitle && createVNode("div", {
						"key": "subtitle",
						"class": "v-stepper-item__subtitle"
					}, [slots.subtitle?.(slotProps.value) ?? props.subtitle]),
					slots.default?.(slotProps.value)
				])
			]), [[
				resolveDirective("ripple"),
				props.ripple && props.editable,
				null
			]]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VStepper/VStepperWindow.js
var makeVStepperWindowProps = propsFactory({ ...omit(makeVWindowProps(), [
	"continuous",
	"nextIcon",
	"prevIcon",
	"showArrows",
	"touch",
	"mandatory"
]) }, "VStepperWindow");
var VStepperWindow = genericComponent()({
	name: "VStepperWindow",
	props: makeVStepperWindowProps(),
	emits: { "update:modelValue": (v) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const group = inject(VStepperSymbol, null);
		const _model = useProxiedModel(props, "modelValue");
		const model = computed({
			get() {
				if (_model.value != null || !group) return _model.value;
				return group.items.value.find((item) => group.selected.value.includes(item.id))?.value;
			},
			set(val) {
				_model.value = val;
			}
		});
		useRender(() => {
			return createVNode(VWindow, mergeProps({ "_as": "VStepperWindow" }, VWindow.filterProps(props), {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"class": ["v-stepper-window", props.class],
				"style": props.style,
				"mandatory": false,
				"touch": false
			}), slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VStepper/VStepperWindowItem.js
var makeVStepperWindowItemProps = propsFactory({ ...makeVWindowItemProps() }, "VStepperWindowItem");
var VStepperWindowItem = genericComponent()({
	name: "VStepperWindowItem",
	props: makeVStepperWindowItemProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => {
			return createVNode(VWindowItem, mergeProps({ "_as": "VStepperWindowItem" }, VWindowItem.filterProps(props), {
				"class": ["v-stepper-window-item", props.class],
				"style": props.style
			}), slots);
		});
		return {};
	}
});
var makeVStepperProps = propsFactory({
	...propsFactory({
		altLabels: Boolean,
		bgColor: String,
		completeIcon: IconValue,
		editIcon: IconValue,
		editable: Boolean,
		errorIcon: IconValue,
		hideActions: Boolean,
		items: {
			type: Array,
			default: () => []
		},
		itemTitle: {
			type: String,
			default: "title"
		},
		itemValue: {
			type: String,
			default: "value"
		},
		nonLinear: Boolean,
		flat: Boolean,
		...makeDisplayProps()
	}, "Stepper")(),
	...makeGroupProps({
		mandatory: "force",
		selectedClass: "v-stepper-item--selected"
	}),
	...makeVSheetProps(),
	...pick(makeVStepperActionsProps(), ["prevText", "nextText"])
}, "VStepper");
var VStepper = genericComponent()({
	name: "VStepper",
	props: makeVStepperProps(),
	emits: { "update:modelValue": (v) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const { items: _items, next, prev, selected } = useGroup(props, VStepperSymbol);
		const { displayClasses, mobile } = useDisplay(props);
		const { completeIcon, editIcon, errorIcon, color, editable, prevText, nextText } = toRefs(props);
		const items = computed(() => props.items.map((item, index) => {
			return {
				title: getPropertyFromItem(item, props.itemTitle, item),
				value: getPropertyFromItem(item, props.itemValue, index + 1),
				raw: item
			};
		}));
		const activeIndex = computed(() => {
			return _items.value.findIndex((item) => selected.value.includes(item.id));
		});
		const disabled = computed(() => {
			if (props.disabled) return props.disabled;
			if (activeIndex.value === 0) return "prev";
			if (activeIndex.value === _items.value.length - 1) return "next";
			return false;
		});
		provideDefaults({
			VStepperItem: {
				editable,
				errorIcon,
				completeIcon,
				editIcon,
				prevText,
				nextText
			},
			VStepperActions: {
				color,
				disabled,
				prevText,
				nextText
			}
		});
		useRender(() => {
			const sheetProps = VSheet.filterProps(props);
			const hasHeader = !!(slots.header || props.items.length);
			const hasWindow = props.items.length > 0;
			const hasActions = !props.hideActions && !!(hasWindow || slots.actions);
			return createVNode(VSheet, mergeProps(sheetProps, {
				"color": props.bgColor,
				"class": [
					"v-stepper",
					{
						"v-stepper--alt-labels": props.altLabels,
						"v-stepper--flat": props.flat,
						"v-stepper--non-linear": props.nonLinear,
						"v-stepper--mobile": mobile.value
					},
					displayClasses.value,
					props.class
				],
				"style": props.style
			}), { default: () => [
				hasHeader && createVNode(VStepperHeader, { "key": "stepper-header" }, { default: () => [items.value.map((_ref2, index) => {
					let { raw, ...item } = _ref2;
					return createVNode(Fragment, null, [!!index && createVNode(VDivider, null, null), createVNode(VStepperItem, item, {
						default: slots[`header-item.${item.value}`] ?? slots.header,
						icon: slots.icon,
						title: slots.title,
						subtitle: slots.subtitle
					})]);
				})] }),
				hasWindow && createVNode(VStepperWindow, { "key": "stepper-window" }, { default: () => [items.value.map((item) => createVNode(VStepperWindowItem, { "value": item.value }, { default: () => slots[`item.${item.value}`]?.(item) ?? slots.item?.(item) }))] }),
				slots.default?.({
					prev,
					next
				}),
				hasActions && (slots.actions?.({
					next,
					prev
				}) ?? createVNode(VStepperActions, {
					"key": "stepper-actions",
					"onClick:prev": prev,
					"onClick:next": next
				}, slots))
			] });
		});
		return {
			prev,
			next
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSwitch/VSwitch.js
var makeVSwitchProps = propsFactory({
	indeterminate: Boolean,
	inset: Boolean,
	flat: Boolean,
	loading: {
		type: [Boolean, String],
		default: false
	},
	...makeVInputProps(),
	...makeVSelectionControlProps()
}, "VSwitch");
var VSwitch = genericComponent()({
	name: "VSwitch",
	inheritAttrs: false,
	props: makeVSwitchProps(),
	emits: {
		"update:focused": (focused) => true,
		"update:modelValue": (value) => true,
		"update:indeterminate": (value) => true
	},
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const indeterminate = useProxiedModel(props, "indeterminate");
		const model = useProxiedModel(props, "modelValue");
		const { loaderClasses } = useLoader(props);
		const { isFocused, focus, blur } = useFocus(props);
		const control = ref();
		const isForcedColorsModeActive = IN_BROWSER && window.matchMedia("(forced-colors: active)").matches;
		const loaderColor = toRef(() => {
			return typeof props.loading === "string" && props.loading !== "" ? props.loading : props.color;
		});
		const uid = useId();
		const id = toRef(() => props.id || `switch-${uid}`);
		function onChange() {
			if (indeterminate.value) indeterminate.value = false;
		}
		function onTrackClick(e) {
			e.stopPropagation();
			e.preventDefault();
			control.value?.input?.click();
		}
		useRender(() => {
			const [rootAttrs, controlAttrs] = filterInputAttrs(attrs);
			const inputProps = VInput.filterProps(props);
			const controlProps = VSelectionControl.filterProps(props);
			return createVNode(VInput, mergeProps({ "class": [
				"v-switch",
				{ "v-switch--flat": props.flat },
				{ "v-switch--inset": props.inset },
				{ "v-switch--indeterminate": indeterminate.value },
				loaderClasses.value,
				props.class
			] }, rootAttrs, inputProps, {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"id": id.value,
				"focused": isFocused.value,
				"style": props.style
			}), {
				...slots,
				default: (_ref2) => {
					let { id, messagesId, isDisabled, isReadonly, isValid } = _ref2;
					const slotProps = {
						model,
						isValid
					};
					return createVNode(VSelectionControl, mergeProps({ "ref": control }, controlProps, {
						"modelValue": model.value,
						"onUpdate:modelValue": [($event) => model.value = $event, onChange],
						"id": id.value,
						"aria-describedby": messagesId.value,
						"type": "checkbox",
						"aria-checked": indeterminate.value ? "mixed" : void 0,
						"disabled": isDisabled.value,
						"readonly": isReadonly.value,
						"onFocus": focus,
						"onBlur": blur
					}, controlAttrs), {
						...slots,
						default: (_ref3) => {
							let { backgroundColorClasses, backgroundColorStyles } = _ref3;
							return createVNode("div", {
								"class": ["v-switch__track", !isForcedColorsModeActive ? backgroundColorClasses.value : void 0],
								"style": backgroundColorStyles.value,
								"onClick": onTrackClick
							}, [slots["track-true"] && createVNode("div", {
								"key": "prepend",
								"class": "v-switch__track-true"
							}, [slots["track-true"](slotProps)]), slots["track-false"] && createVNode("div", {
								"key": "append",
								"class": "v-switch__track-false"
							}, [slots["track-false"](slotProps)])]);
						},
						input: (_ref4) => {
							let { inputNode, icon, backgroundColorClasses, backgroundColorStyles } = _ref4;
							return createVNode(Fragment, null, [inputNode, createVNode("div", {
								"class": [
									"v-switch__thumb",
									{ "v-switch__thumb--filled": icon || props.loading },
									props.inset || isForcedColorsModeActive ? void 0 : backgroundColorClasses.value
								],
								"style": props.inset ? void 0 : backgroundColorStyles.value
							}, [slots.thumb ? createVNode(VDefaultsProvider, { "defaults": { VIcon: {
								icon,
								size: "x-small"
							} } }, { default: () => [slots.thumb({
								...slotProps,
								icon
							})] }) : createVNode(VScaleTransition, null, { default: () => [!props.loading ? icon && createVNode(VIcon, {
								"key": String(icon),
								"icon": icon,
								"size": "x-small"
							}, null) : createVNode(LoaderSlot, {
								"name": "v-switch",
								"active": true,
								"color": isValid.value === false ? void 0 : loaderColor.value
							}, { default: (slotProps) => slots.loader ? slots.loader(slotProps) : createVNode(VProgressCircular, {
								"active": slotProps.isActive,
								"color": slotProps.color,
								"indeterminate": true,
								"size": "16",
								"width": "2"
							}, null) })] })])]);
						}
					});
				}
			});
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VSystemBar/VSystemBar.js
var makeVSystemBarProps = propsFactory({
	color: String,
	height: [Number, String],
	window: Boolean,
	...makeComponentProps(),
	...makeElevationProps(),
	...makeLayoutItemProps(),
	...makeRoundedProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VSystemBar");
var VSystemBar = genericComponent()({
	name: "VSystemBar",
	props: makeVSystemBarProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.color);
		const { elevationClasses } = useElevation(props);
		const { roundedClasses } = useRounded(props);
		const { ssrBootStyles } = useSsrBoot();
		const height = computed(() => props.height ?? (props.window ? 32 : 24));
		const { layoutItemStyles } = useLayoutItem({
			id: props.name,
			order: computed(() => parseInt(props.order, 10)),
			position: shallowRef("top"),
			layoutSize: height,
			elementSize: height,
			active: computed(() => true),
			absolute: toRef(() => props.absolute)
		});
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-system-bar",
				{ "v-system-bar--window": props.window },
				themeClasses.value,
				backgroundColorClasses.value,
				elevationClasses.value,
				roundedClasses.value,
				props.class
			],
			"style": [
				backgroundColorStyles.value,
				layoutItemStyles.value,
				ssrBootStyles.value,
				props.style
			]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTabs/shared.js
var VTabsSymbol = Symbol.for("vuetify:v-tabs");
//#endregion
//#region node_modules/vuetify/lib/components/VTabs/VTab.js
var makeVTabProps = propsFactory({
	fixed: Boolean,
	sliderColor: String,
	hideSlider: Boolean,
	direction: {
		type: String,
		default: "horizontal"
	},
	...omit(makeVBtnProps({
		selectedClass: "v-tab--selected",
		variant: "text"
	}), [
		"active",
		"block",
		"flat",
		"location",
		"position",
		"symbol"
	])
}, "VTab");
var VTab = genericComponent()({
	name: "VTab",
	props: makeVTabProps(),
	setup(props, _ref) {
		let { slots, attrs } = _ref;
		const { textColorClasses: sliderColorClasses, textColorStyles: sliderColorStyles } = useTextColor(() => props.sliderColor);
		const rootEl = ref();
		const sliderEl = ref();
		const isHorizontal = computed(() => props.direction === "horizontal");
		const isSelected = computed(() => rootEl.value?.group?.isSelected.value ?? false);
		function updateSlider(_ref2) {
			let { value } = _ref2;
			if (value) {
				const prevEl = rootEl.value?.$el.parentElement?.querySelector(".v-tab--selected .v-tab__slider");
				const nextEl = sliderEl.value;
				if (!prevEl || !nextEl) return;
				const color = getComputedStyle(prevEl).color;
				const prevBox = prevEl.getBoundingClientRect();
				const nextBox = nextEl.getBoundingClientRect();
				const xy = isHorizontal.value ? "x" : "y";
				const XY = isHorizontal.value ? "X" : "Y";
				const rightBottom = isHorizontal.value ? "right" : "bottom";
				const widthHeight = isHorizontal.value ? "width" : "height";
				const delta = prevBox[xy] > nextBox[xy] ? prevBox[rightBottom] - nextBox[rightBottom] : prevBox[xy] - nextBox[xy];
				const origin = Math.sign(delta) > 0 ? isHorizontal.value ? "right" : "bottom" : Math.sign(delta) < 0 ? isHorizontal.value ? "left" : "top" : "center";
				const scale = (Math.abs(delta) + (Math.sign(delta) < 0 ? prevBox[widthHeight] : nextBox[widthHeight])) / Math.max(prevBox[widthHeight], nextBox[widthHeight]) || 0;
				const initialScale = prevBox[widthHeight] / nextBox[widthHeight] || 0;
				const sigma = 1.5;
				animate(nextEl, {
					backgroundColor: [color, "currentcolor"],
					transform: [
						`translate${XY}(${delta}px) scale${XY}(${initialScale})`,
						`translate${XY}(${delta / sigma}px) scale${XY}(${(scale - 1) / sigma + 1})`,
						"none"
					],
					transformOrigin: Array(3).fill(origin)
				}, {
					duration: 225,
					easing: standardEasing
				});
			}
		}
		useRender(() => {
			const btnProps = VBtn.filterProps(props);
			return createVNode(VBtn, mergeProps({
				"symbol": VTabsSymbol,
				"ref": rootEl,
				"class": ["v-tab", props.class],
				"style": props.style,
				"tabindex": isSelected.value ? 0 : -1,
				"role": "tab",
				"aria-selected": String(isSelected.value),
				"active": false
			}, btnProps, attrs, {
				"block": props.fixed,
				"maxWidth": props.fixed ? 300 : void 0,
				"onGroup:selected": updateSlider
			}), {
				...slots,
				default: () => createVNode(Fragment, null, [slots.default?.() ?? props.text, !props.hideSlider && createVNode("div", {
					"ref": sliderEl,
					"class": ["v-tab__slider", sliderColorClasses.value],
					"style": sliderColorStyles.value
				}, null)])
			});
		});
		return forwardRefs({}, rootEl);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTabs/VTabsWindow.js
var makeVTabsWindowProps = propsFactory({ ...omit(makeVWindowProps(), [
	"continuous",
	"nextIcon",
	"prevIcon",
	"showArrows",
	"touch",
	"mandatory"
]) }, "VTabsWindow");
var VTabsWindow = genericComponent()({
	name: "VTabsWindow",
	props: makeVTabsWindowProps(),
	emits: { "update:modelValue": (v) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const group = inject(VTabsSymbol, null);
		const _model = useProxiedModel(props, "modelValue");
		const model = computed({
			get() {
				if (_model.value != null || !group) return _model.value;
				return group.items.value.find((item) => group.selected.value.includes(item.id))?.value;
			},
			set(val) {
				_model.value = val;
			}
		});
		useRender(() => {
			return createVNode(VWindow, mergeProps({ "_as": "VTabsWindow" }, VWindow.filterProps(props), {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"class": ["v-tabs-window", props.class],
				"style": props.style,
				"mandatory": false,
				"touch": false
			}), slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTabs/VTabsWindowItem.js
var makeVTabsWindowItemProps = propsFactory({ ...makeVWindowItemProps() }, "VTabsWindowItem");
var VTabsWindowItem = genericComponent()({
	name: "VTabsWindowItem",
	props: makeVTabsWindowItemProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		useRender(() => {
			return createVNode(VWindowItem, mergeProps({ "_as": "VTabsWindowItem" }, VWindowItem.filterProps(props), {
				"class": ["v-tabs-window-item", props.class],
				"style": props.style
			}), slots);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTabs/VTabs.js
function parseItems(items) {
	if (!items) return [];
	return items.map((item) => {
		if (!isObject(item)) return {
			text: item,
			value: item
		};
		return item;
	});
}
var makeVTabsProps = propsFactory({
	alignTabs: {
		type: String,
		default: "start"
	},
	color: String,
	fixedTabs: Boolean,
	items: {
		type: Array,
		default: () => []
	},
	stacked: Boolean,
	bgColor: String,
	grow: Boolean,
	height: {
		type: [Number, String],
		default: void 0
	},
	hideSlider: Boolean,
	sliderColor: String,
	...makeVSlideGroupProps({
		mandatory: "force",
		selectedClass: "v-tab-item--selected"
	}),
	...makeDensityProps(),
	...makeTagProps()
}, "VTabs");
var VTabs = genericComponent()({
	name: "VTabs",
	props: makeVTabsProps(),
	emits: { "update:modelValue": (v) => true },
	setup(props, _ref) {
		let { attrs, slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const items = computed(() => parseItems(props.items));
		const { densityClasses } = useDensity(props);
		const { backgroundColorClasses, backgroundColorStyles } = useBackgroundColor(() => props.bgColor);
		const { scopeId } = useScopeId();
		provideDefaults({ VTab: {
			color: toRef(() => props.color),
			direction: toRef(() => props.direction),
			stacked: toRef(() => props.stacked),
			fixed: toRef(() => props.fixedTabs),
			sliderColor: toRef(() => props.sliderColor),
			hideSlider: toRef(() => props.hideSlider)
		} });
		useRender(() => {
			const slideGroupProps = VSlideGroup.filterProps(props);
			const hasWindow = !!(slots.window || props.items.length > 0);
			return createVNode(Fragment, null, [createVNode(VSlideGroup, mergeProps(slideGroupProps, {
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"class": [
					"v-tabs",
					`v-tabs--${props.direction}`,
					`v-tabs--align-tabs-${props.alignTabs}`,
					{
						"v-tabs--fixed-tabs": props.fixedTabs,
						"v-tabs--grow": props.grow,
						"v-tabs--stacked": props.stacked
					},
					densityClasses.value,
					backgroundColorClasses.value,
					props.class
				],
				"style": [
					{ "--v-tabs-height": convertToUnit(props.height) },
					backgroundColorStyles.value,
					props.style
				],
				"role": "tablist",
				"symbol": VTabsSymbol
			}, scopeId, attrs), { default: () => [slots.default?.() ?? items.value.map((item) => slots.tab?.({ item }) ?? createVNode(VTab, mergeProps(item, {
				"key": item.text,
				"value": item.value
			}), { default: slots[`tab.${item.value}`] ? () => slots[`tab.${item.value}`]?.({ item }) : void 0 }))] }), hasWindow && createVNode(VTabsWindow, mergeProps({
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"key": "tabs-window"
			}, scopeId), { default: () => [items.value.map((item) => slots.item?.({ item }) ?? createVNode(VTabsWindowItem, { "value": item.value }, { default: () => slots[`item.${item.value}`]?.({ item }) })), slots.window?.()] })]);
		});
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTextarea/VTextarea.js
var makeVTextareaProps = propsFactory({
	autoGrow: Boolean,
	autofocus: Boolean,
	counter: [
		Boolean,
		Number,
		String
	],
	counterValue: Function,
	prefix: String,
	placeholder: String,
	persistentPlaceholder: Boolean,
	persistentCounter: Boolean,
	noResize: Boolean,
	rows: {
		type: [Number, String],
		default: 5,
		validator: (v) => !isNaN(parseFloat(v))
	},
	maxRows: {
		type: [Number, String],
		validator: (v) => !isNaN(parseFloat(v))
	},
	suffix: String,
	modelModifiers: Object,
	...makeVInputProps(),
	...makeVFieldProps()
}, "VTextarea");
var VTextarea = genericComponent()({
	name: "VTextarea",
	directives: { Intersect },
	inheritAttrs: false,
	props: makeVTextareaProps(),
	emits: {
		"click:control": (e) => true,
		"mousedown:control": (e) => true,
		"update:focused": (focused) => true,
		"update:modelValue": (val) => true
	},
	setup(props, _ref) {
		let { attrs, emit, slots } = _ref;
		const model = useProxiedModel(props, "modelValue");
		const { isFocused, focus, blur } = useFocus(props);
		const counterValue = computed(() => {
			return typeof props.counterValue === "function" ? props.counterValue(model.value) : (model.value || "").toString().length;
		});
		const max = computed(() => {
			if (attrs.maxlength) return attrs.maxlength;
			if (!props.counter || typeof props.counter !== "number" && typeof props.counter !== "string") return void 0;
			return props.counter;
		});
		function onIntersect(isIntersecting, entries) {
			if (!props.autofocus || !isIntersecting) return;
			entries[0].target?.focus?.();
		}
		const vInputRef = ref();
		const vFieldRef = ref();
		const controlHeight = shallowRef("");
		const textareaRef = ref();
		const isActive = computed(() => props.persistentPlaceholder || isFocused.value || props.active);
		function onFocus() {
			if (textareaRef.value !== document.activeElement) textareaRef.value?.focus();
			if (!isFocused.value) focus();
		}
		function onControlClick(e) {
			onFocus();
			emit("click:control", e);
		}
		function onControlMousedown(e) {
			emit("mousedown:control", e);
		}
		function onClear(e) {
			e.stopPropagation();
			onFocus();
			nextTick(() => {
				model.value = "";
				callEvent(props["onClick:clear"], e);
			});
		}
		function onInput(e) {
			const el = e.target;
			model.value = el.value;
			if (props.modelModifiers?.trim) {
				const caretPosition = [el.selectionStart, el.selectionEnd];
				nextTick(() => {
					el.selectionStart = caretPosition[0];
					el.selectionEnd = caretPosition[1];
				});
			}
		}
		const sizerRef = ref();
		const rows = ref(Number(props.rows));
		const isPlainOrUnderlined = computed(() => ["plain", "underlined"].includes(props.variant));
		watchEffect(() => {
			if (!props.autoGrow) rows.value = Number(props.rows);
		});
		function calculateInputHeight() {
			if (!props.autoGrow) return;
			nextTick(() => {
				if (!sizerRef.value || !vFieldRef.value) return;
				const style = getComputedStyle(sizerRef.value);
				const fieldStyle = getComputedStyle(vFieldRef.value.$el);
				const padding = parseFloat(style.getPropertyValue("--v-field-padding-top")) + parseFloat(style.getPropertyValue("--v-input-padding-top")) + parseFloat(style.getPropertyValue("--v-field-padding-bottom"));
				const height = sizerRef.value.scrollHeight;
				const lineHeight = parseFloat(style.lineHeight);
				const minHeight = Math.max(parseFloat(props.rows) * lineHeight + padding, parseFloat(fieldStyle.getPropertyValue("--v-input-control-height")));
				const maxHeight = parseFloat(props.maxRows) * lineHeight + padding || Infinity;
				const newHeight = clamp(height ?? 0, minHeight, maxHeight);
				rows.value = Math.floor((newHeight - padding) / lineHeight);
				controlHeight.value = convertToUnit(newHeight);
			});
		}
		onMounted(calculateInputHeight);
		watch(model, calculateInputHeight);
		watch(() => props.rows, calculateInputHeight);
		watch(() => props.maxRows, calculateInputHeight);
		watch(() => props.density, calculateInputHeight);
		let observer;
		watch(sizerRef, (val) => {
			if (val) {
				observer = new ResizeObserver(calculateInputHeight);
				observer.observe(sizerRef.value);
			} else observer?.disconnect();
		});
		onBeforeUnmount(() => {
			observer?.disconnect();
		});
		useRender(() => {
			const hasCounter = !!(slots.counter || props.counter || props.counterValue);
			const hasDetails = !!(hasCounter || slots.details);
			const [rootAttrs, inputAttrs] = filterInputAttrs(attrs);
			const { modelValue: _, ...inputProps } = VInput.filterProps(props);
			const fieldProps = VField.filterProps(props);
			return createVNode(VInput, mergeProps({
				"ref": vInputRef,
				"modelValue": model.value,
				"onUpdate:modelValue": ($event) => model.value = $event,
				"class": [
					"v-textarea v-text-field",
					{
						"v-textarea--prefixed": props.prefix,
						"v-textarea--suffixed": props.suffix,
						"v-text-field--prefixed": props.prefix,
						"v-text-field--suffixed": props.suffix,
						"v-textarea--auto-grow": props.autoGrow,
						"v-textarea--no-resize": props.noResize || props.autoGrow,
						"v-input--plain-underlined": isPlainOrUnderlined.value
					},
					props.class
				],
				"style": props.style
			}, rootAttrs, inputProps, {
				"centerAffix": rows.value === 1 && !isPlainOrUnderlined.value,
				"focused": isFocused.value
			}), {
				...slots,
				default: (_ref2) => {
					let { id, isDisabled, isDirty, isReadonly, isValid } = _ref2;
					return createVNode(VField, mergeProps({
						"ref": vFieldRef,
						"style": { "--v-textarea-control-height": controlHeight.value },
						"onClick": onControlClick,
						"onMousedown": onControlMousedown,
						"onClick:clear": onClear,
						"onClick:prependInner": props["onClick:prependInner"],
						"onClick:appendInner": props["onClick:appendInner"]
					}, fieldProps, {
						"id": id.value,
						"active": isActive.value || isDirty.value,
						"centerAffix": rows.value === 1 && !isPlainOrUnderlined.value,
						"dirty": isDirty.value || props.dirty,
						"disabled": isDisabled.value,
						"focused": isFocused.value,
						"error": isValid.value === false
					}), {
						...slots,
						default: (_ref3) => {
							let { props: { class: fieldClass, ...slotProps } } = _ref3;
							return createVNode(Fragment, null, [
								props.prefix && createVNode("span", { "class": "v-text-field__prefix" }, [props.prefix]),
								withDirectives(createVNode("textarea", mergeProps({
									"ref": textareaRef,
									"class": fieldClass,
									"value": model.value,
									"onInput": onInput,
									"autofocus": props.autofocus,
									"readonly": isReadonly.value,
									"disabled": isDisabled.value,
									"placeholder": props.placeholder,
									"rows": props.rows,
									"name": props.name,
									"onFocus": onFocus,
									"onBlur": blur
								}, slotProps, inputAttrs), null), [[
									resolveDirective("intersect"),
									{ handler: onIntersect },
									null,
									{ once: true }
								]]),
								props.autoGrow && withDirectives(createVNode("textarea", {
									"class": [fieldClass, "v-textarea__sizer"],
									"id": `${slotProps.id}-sizer`,
									"onUpdate:modelValue": ($event) => model.value = $event,
									"ref": sizerRef,
									"readonly": true,
									"aria-hidden": "true"
								}, null), [[vModelText, model.value]]),
								props.suffix && createVNode("span", { "class": "v-text-field__suffix" }, [props.suffix])
							]);
						}
					});
				},
				details: hasDetails ? (slotProps) => createVNode(Fragment, null, [slots.details?.(slotProps), hasCounter && createVNode(Fragment, null, [createVNode("span", null, null), createVNode(VCounter, {
					"active": props.persistentCounter || isFocused.value,
					"value": counterValue.value,
					"max": max.value,
					"disabled": props.disabled
				}, slots.counter)])]) : void 0
			});
		});
		return forwardRefs({}, vInputRef, vFieldRef, textareaRef);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VThemeProvider/VThemeProvider.js
var makeVThemeProviderProps = propsFactory({
	withBackground: Boolean,
	...makeComponentProps(),
	...makeThemeProps(),
	...makeTagProps()
}, "VThemeProvider");
var VThemeProvider = genericComponent()({
	name: "VThemeProvider",
	props: makeVThemeProviderProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		return () => {
			if (!props.withBackground) return slots.default?.();
			return createVNode(props.tag, {
				"class": [
					"v-theme-provider",
					themeClasses.value,
					props.class
				],
				"style": props.style
			}, { default: () => [slots.default?.()] });
		};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTimeline/VTimelineDivider.js
var makeVTimelineDividerProps = propsFactory({
	dotColor: String,
	fillDot: Boolean,
	hideDot: Boolean,
	icon: IconValue,
	iconColor: String,
	lineColor: String,
	...makeComponentProps(),
	...makeRoundedProps(),
	...makeSizeProps(),
	...makeElevationProps()
}, "VTimelineDivider");
var VTimelineDivider = genericComponent()({
	name: "VTimelineDivider",
	props: makeVTimelineDividerProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { sizeClasses, sizeStyles } = useSize(props, "v-timeline-divider__dot");
		const { backgroundColorStyles, backgroundColorClasses } = useBackgroundColor(() => props.dotColor);
		const { roundedClasses } = useRounded(props, "v-timeline-divider__dot");
		const { elevationClasses } = useElevation(props);
		const { backgroundColorClasses: lineColorClasses, backgroundColorStyles: lineColorStyles } = useBackgroundColor(() => props.lineColor);
		useRender(() => createVNode("div", {
			"class": [
				"v-timeline-divider",
				{ "v-timeline-divider--fill-dot": props.fillDot },
				props.class
			],
			"style": props.style
		}, [
			createVNode("div", {
				"class": ["v-timeline-divider__before", lineColorClasses.value],
				"style": lineColorStyles.value
			}, null),
			!props.hideDot && createVNode("div", {
				"key": "dot",
				"class": [
					"v-timeline-divider__dot",
					elevationClasses.value,
					roundedClasses.value,
					sizeClasses.value
				],
				"style": sizeStyles.value
			}, [createVNode("div", {
				"class": [
					"v-timeline-divider__inner-dot",
					backgroundColorClasses.value,
					roundedClasses.value
				],
				"style": backgroundColorStyles.value
			}, [!slots.default ? createVNode(VIcon, {
				"key": "icon",
				"color": props.iconColor,
				"icon": props.icon,
				"size": props.size
			}, null) : createVNode(VDefaultsProvider, {
				"key": "icon-defaults",
				"disabled": !props.icon,
				"defaults": { VIcon: {
					color: props.iconColor,
					icon: props.icon,
					size: props.size
				} }
			}, slots.default)])]),
			createVNode("div", {
				"class": ["v-timeline-divider__after", lineColorClasses.value],
				"style": lineColorStyles.value
			}, null)
		]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTimeline/VTimelineItem.js
var makeVTimelineItemProps = propsFactory({
	density: String,
	dotColor: String,
	fillDot: Boolean,
	hideDot: Boolean,
	hideOpposite: {
		type: Boolean,
		default: void 0
	},
	icon: IconValue,
	iconColor: String,
	lineInset: [Number, String],
	side: {
		type: String,
		validator: (v) => v == null || ["start", "end"].includes(v)
	},
	...makeComponentProps(),
	...makeDimensionProps(),
	...makeElevationProps(),
	...makeRoundedProps(),
	...makeSizeProps(),
	...makeTagProps()
}, "VTimelineItem");
var VTimelineItem = genericComponent()({
	name: "VTimelineItem",
	props: makeVTimelineItemProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { dimensionStyles } = useDimension(props);
		const dotSize = shallowRef(0);
		const dotRef = ref();
		watch(dotRef, (newValue) => {
			if (!newValue) return;
			dotSize.value = newValue.$el.querySelector(".v-timeline-divider__dot")?.getBoundingClientRect().width ?? 0;
		}, { flush: "post" });
		useRender(() => createVNode("div", {
			"class": [
				"v-timeline-item",
				{
					"v-timeline-item--fill-dot": props.fillDot,
					"v-timeline-item--side-start": props.side === "start",
					"v-timeline-item--side-end": props.side === "end"
				},
				props.class
			],
			"style": [{
				"--v-timeline-dot-size": convertToUnit(dotSize.value),
				"--v-timeline-line-inset": props.lineInset ? `calc(var(--v-timeline-dot-size) / 2 + ${convertToUnit(props.lineInset)})` : convertToUnit(0)
			}, props.style]
		}, [
			createVNode("div", {
				"class": "v-timeline-item__body",
				"style": dimensionStyles.value
			}, [slots.default?.()]),
			createVNode(VTimelineDivider, {
				"ref": dotRef,
				"hideDot": props.hideDot,
				"icon": props.icon,
				"iconColor": props.iconColor,
				"size": props.size,
				"elevation": props.elevation,
				"dotColor": props.dotColor,
				"fillDot": props.fillDot,
				"rounded": props.rounded
			}, { default: slots.icon }),
			props.density !== "compact" && createVNode("div", { "class": "v-timeline-item__opposite" }, [!props.hideOpposite && slots.opposite?.()])
		]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTimeline/VTimeline.js
var makeVTimelineProps = propsFactory({
	align: {
		type: String,
		default: "center",
		validator: (v) => ["center", "start"].includes(v)
	},
	direction: {
		type: String,
		default: "vertical",
		validator: (v) => ["vertical", "horizontal"].includes(v)
	},
	justify: {
		type: String,
		default: "auto",
		validator: (v) => ["auto", "center"].includes(v)
	},
	side: {
		type: String,
		validator: (v) => v == null || ["start", "end"].includes(v)
	},
	lineThickness: {
		type: [String, Number],
		default: 2
	},
	lineColor: String,
	truncateLine: {
		type: String,
		validator: (v) => [
			"start",
			"end",
			"both"
		].includes(v)
	},
	...pick(makeVTimelineItemProps({ lineInset: 0 }), [
		"dotColor",
		"fillDot",
		"hideOpposite",
		"iconColor",
		"lineInset",
		"size"
	]),
	...makeComponentProps(),
	...makeDensityProps(),
	...makeTagProps(),
	...makeThemeProps()
}, "VTimeline");
var VTimeline = genericComponent()({
	name: "VTimeline",
	props: makeVTimelineProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		const { themeClasses } = provideTheme(props);
		const { densityClasses } = useDensity(props);
		const { rtlClasses } = useRtl();
		provideDefaults({
			VTimelineDivider: { lineColor: toRef(() => props.lineColor) },
			VTimelineItem: {
				density: toRef(() => props.density),
				dotColor: toRef(() => props.dotColor),
				fillDot: toRef(() => props.fillDot),
				hideOpposite: toRef(() => props.hideOpposite),
				iconColor: toRef(() => props.iconColor),
				lineColor: toRef(() => props.lineColor),
				lineInset: toRef(() => props.lineInset),
				size: toRef(() => props.size)
			}
		});
		const sideClasses = computed(() => {
			const side = props.side ? props.side : props.density !== "default" ? "end" : null;
			return side && `v-timeline--side-${side}`;
		});
		const truncateClasses = computed(() => {
			const classes = ["v-timeline--truncate-line-start", "v-timeline--truncate-line-end"];
			switch (props.truncateLine) {
				case "both": return classes;
				case "start": return classes[0];
				case "end": return classes[1];
				default: return null;
			}
		});
		useRender(() => createVNode(props.tag, {
			"class": [
				"v-timeline",
				`v-timeline--${props.direction}`,
				`v-timeline--align-${props.align}`,
				`v-timeline--justify-${props.justify}`,
				truncateClasses.value,
				{ "v-timeline--inset-line": !!props.lineInset },
				themeClasses.value,
				densityClasses.value,
				sideClasses.value,
				rtlClasses.value,
				props.class
			],
			"style": [{ "--v-timeline-line-thickness": convertToUnit(props.lineThickness) }, props.style]
		}, slots));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VToolbar/VToolbarItems.js
var makeVToolbarItemsProps = propsFactory({
	...makeComponentProps(),
	...makeVariantProps({ variant: "text" })
}, "VToolbarItems");
var VToolbarItems = genericComponent()({
	name: "VToolbarItems",
	props: makeVToolbarItemsProps(),
	setup(props, _ref) {
		let { slots } = _ref;
		provideDefaults({ VBtn: {
			color: toRef(() => props.color),
			height: "inherit",
			variant: toRef(() => props.variant)
		} });
		useRender(() => createVNode("div", {
			"class": ["v-toolbar-items", props.class],
			"style": props.style
		}, [slots.default?.()]));
		return {};
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VTooltip/VTooltip.js
var makeVTooltipProps = propsFactory({
	id: String,
	interactive: Boolean,
	text: String,
	...omit(makeVOverlayProps({
		closeOnBack: false,
		location: "end",
		locationStrategy: "connected",
		eager: true,
		minWidth: 0,
		offset: 10,
		openOnClick: false,
		openOnHover: true,
		origin: "auto",
		scrim: false,
		scrollStrategy: "reposition",
		transition: null
	}), ["absolute", "persistent"])
}, "VTooltip");
var VTooltip = genericComponent()({
	name: "VTooltip",
	props: makeVTooltipProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const isActive = useProxiedModel(props, "modelValue");
		const { scopeId } = useScopeId();
		const uid = useId();
		const id = toRef(() => props.id || `v-tooltip-${uid}`);
		const overlay = ref();
		const location = computed(() => {
			return props.location.split(" ").length > 1 ? props.location : props.location + " center";
		});
		const origin = computed(() => {
			return props.origin === "auto" || props.origin === "overlap" || props.origin.split(" ").length > 1 || props.location.split(" ").length > 1 ? props.origin : props.origin + " center";
		});
		const transition = toRef(() => {
			if (props.transition != null) return props.transition;
			return isActive.value ? "scale-transition" : "fade-transition";
		});
		const activatorProps = computed(() => mergeProps({ "aria-describedby": id.value }, props.activatorProps));
		useRender(() => {
			const overlayProps = VOverlay.filterProps(props);
			return createVNode(VOverlay, mergeProps({
				"ref": overlay,
				"class": [
					"v-tooltip",
					{ "v-tooltip--interactive": props.interactive },
					props.class
				],
				"style": props.style,
				"id": id.value
			}, overlayProps, {
				"modelValue": isActive.value,
				"onUpdate:modelValue": ($event) => isActive.value = $event,
				"transition": transition.value,
				"absolute": true,
				"location": location.value,
				"origin": origin.value,
				"persistent": true,
				"role": "tooltip",
				"activatorProps": activatorProps.value,
				"_disableGlobalStack": true
			}, scopeId), {
				activator: slots.activator,
				default: function() {
					for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) args[_key] = arguments[_key];
					return slots.default?.(...args) ?? props.text;
				}
			});
		});
		return forwardRefs({}, overlay);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/VValidation/VValidation.js
var VValidation = genericComponent()({
	name: "VValidation",
	props: makeValidationProps(),
	emits: { "update:modelValue": (value) => true },
	setup(props, _ref) {
		let { slots } = _ref;
		const validation = useValidation(props, "validation");
		return () => slots.default?.(validation);
	}
});
//#endregion
//#region node_modules/vuetify/lib/components/index.js
var components_exports = /* @__PURE__ */ __exportAll({
	VAlert: () => VAlert,
	VAlertTitle: () => VAlertTitle,
	VApp: () => VApp,
	VAppBar: () => VAppBar,
	VAppBarNavIcon: () => VAppBarNavIcon,
	VAppBarTitle: () => VAppBarTitle,
	VAutocomplete: () => VAutocomplete,
	VAvatar: () => VAvatar,
	VBadge: () => VBadge,
	VBanner: () => VBanner,
	VBannerActions: () => VBannerActions,
	VBannerText: () => VBannerText,
	VBottomNavigation: () => VBottomNavigation,
	VBottomSheet: () => VBottomSheet,
	VBreadcrumbs: () => VBreadcrumbs,
	VBreadcrumbsDivider: () => VBreadcrumbsDivider,
	VBreadcrumbsItem: () => VBreadcrumbsItem,
	VBtn: () => VBtn,
	VBtnGroup: () => VBtnGroup,
	VBtnToggle: () => VBtnToggle,
	VCard: () => VCard,
	VCardActions: () => VCardActions,
	VCardItem: () => VCardItem,
	VCardSubtitle: () => VCardSubtitle,
	VCardText: () => VCardText,
	VCardTitle: () => VCardTitle,
	VCarousel: () => VCarousel,
	VCarouselItem: () => VCarouselItem,
	VCheckbox: () => VCheckbox,
	VCheckboxBtn: () => VCheckboxBtn,
	VChip: () => VChip,
	VChipGroup: () => VChipGroup,
	VClassIcon: () => VClassIcon,
	VCode: () => VCode,
	VCol: () => VCol,
	VColorPicker: () => VColorPicker,
	VCombobox: () => VCombobox,
	VComponentIcon: () => VComponentIcon,
	VConfirmEdit: () => VConfirmEdit,
	VContainer: () => VContainer,
	VCounter: () => VCounter,
	VDataIterator: () => VDataIterator,
	VDataTable: () => VDataTable,
	VDataTableFooter: () => VDataTableFooter,
	VDataTableHeaders: () => VDataTableHeaders,
	VDataTableRow: () => VDataTableRow,
	VDataTableRows: () => VDataTableRows,
	VDataTableServer: () => VDataTableServer,
	VDataTableVirtual: () => VDataTableVirtual,
	VDatePicker: () => VDatePicker,
	VDatePickerControls: () => VDatePickerControls,
	VDatePickerHeader: () => VDatePickerHeader,
	VDatePickerMonth: () => VDatePickerMonth,
	VDatePickerMonths: () => VDatePickerMonths,
	VDatePickerYears: () => VDatePickerYears,
	VDefaultsProvider: () => VDefaultsProvider,
	VDialog: () => VDialog,
	VDialogBottomTransition: () => VDialogBottomTransition,
	VDialogTopTransition: () => VDialogTopTransition,
	VDialogTransition: () => VDialogTransition,
	VDivider: () => VDivider,
	VEmptyState: () => VEmptyState,
	VExpandTransition: () => VExpandTransition,
	VExpandXTransition: () => VExpandXTransition,
	VExpansionPanel: () => VExpansionPanel,
	VExpansionPanelText: () => VExpansionPanelText,
	VExpansionPanelTitle: () => VExpansionPanelTitle,
	VExpansionPanels: () => VExpansionPanels,
	VFab: () => VFab,
	VFabTransition: () => VFabTransition,
	VFadeTransition: () => VFadeTransition,
	VField: () => VField,
	VFieldLabel: () => VFieldLabel,
	VFileInput: () => VFileInput,
	VFooter: () => VFooter,
	VForm: () => VForm,
	VHover: () => VHover,
	VIcon: () => VIcon,
	VImg: () => VImg,
	VInfiniteScroll: () => VInfiniteScroll,
	VInput: () => VInput,
	VItem: () => VItem,
	VItemGroup: () => VItemGroup,
	VKbd: () => VKbd,
	VLabel: () => VLabel,
	VLayout: () => VLayout,
	VLayoutItem: () => VLayoutItem,
	VLazy: () => VLazy,
	VLigatureIcon: () => VLigatureIcon,
	VList: () => VList,
	VListGroup: () => VListGroup,
	VListImg: () => VListImg,
	VListItem: () => VListItem,
	VListItemAction: () => VListItemAction,
	VListItemMedia: () => VListItemMedia,
	VListItemSubtitle: () => VListItemSubtitle,
	VListItemTitle: () => VListItemTitle,
	VListSubheader: () => VListSubheader,
	VLocaleProvider: () => VLocaleProvider,
	VMain: () => VMain,
	VMenu: () => VMenu,
	VMessages: () => VMessages,
	VNavigationDrawer: () => VNavigationDrawer,
	VNoSsr: () => VNoSsr,
	VNumberInput: () => VNumberInput,
	VOtpInput: () => VOtpInput,
	VOverlay: () => VOverlay,
	VPagination: () => VPagination,
	VParallax: () => VParallax,
	VProgressCircular: () => VProgressCircular,
	VProgressLinear: () => VProgressLinear,
	VRadio: () => VRadio,
	VRadioGroup: () => VRadioGroup,
	VRangeSlider: () => VRangeSlider,
	VRating: () => VRating,
	VResponsive: () => VResponsive,
	VRow: () => VRow,
	VScaleTransition: () => VScaleTransition,
	VScrollXReverseTransition: () => VScrollXReverseTransition,
	VScrollXTransition: () => VScrollXTransition,
	VScrollYReverseTransition: () => VScrollYReverseTransition,
	VScrollYTransition: () => VScrollYTransition,
	VSelect: () => VSelect,
	VSelectionControl: () => VSelectionControl,
	VSelectionControlGroup: () => VSelectionControlGroup,
	VSheet: () => VSheet,
	VSkeletonLoader: () => VSkeletonLoader,
	VSlideGroup: () => VSlideGroup,
	VSlideGroupItem: () => VSlideGroupItem,
	VSlideXReverseTransition: () => VSlideXReverseTransition,
	VSlideXTransition: () => VSlideXTransition,
	VSlideYReverseTransition: () => VSlideYReverseTransition,
	VSlideYTransition: () => VSlideYTransition,
	VSlider: () => VSlider,
	VSnackbar: () => VSnackbar,
	VSnackbarQueue: () => VSnackbarQueue,
	VSpacer: () => VSpacer,
	VSparkline: () => VSparkline,
	VSpeedDial: () => VSpeedDial,
	VStepper: () => VStepper,
	VStepperActions: () => VStepperActions,
	VStepperHeader: () => VStepperHeader,
	VStepperItem: () => VStepperItem,
	VStepperWindow: () => VStepperWindow,
	VStepperWindowItem: () => VStepperWindowItem,
	VSvgIcon: () => VSvgIcon,
	VSwitch: () => VSwitch,
	VSystemBar: () => VSystemBar,
	VTab: () => VTab,
	VTable: () => VTable,
	VTabs: () => VTabs,
	VTabsWindow: () => VTabsWindow,
	VTabsWindowItem: () => VTabsWindowItem,
	VTextField: () => VTextField,
	VTextarea: () => VTextarea,
	VThemeProvider: () => VThemeProvider,
	VTimeline: () => VTimeline,
	VTimelineItem: () => VTimelineItem,
	VToolbar: () => VToolbar,
	VToolbarItems: () => VToolbarItems,
	VToolbarTitle: () => VToolbarTitle,
	VTooltip: () => VTooltip,
	VValidation: () => VValidation,
	VVirtualScroll: () => VVirtualScroll,
	VWindow: () => VWindow,
	VWindowItem: () => VWindowItem
});
//#endregion
//#region node_modules/vuetify/lib/directives/mutate/index.js
function mounted$2(el, binding) {
	const modifiers = binding.modifiers || {};
	const value = binding.value;
	const { once, immediate, ...modifierKeys } = modifiers;
	const defaultValue = !Object.keys(modifierKeys).length;
	const { handler, options } = typeof value === "object" ? value : {
		handler: value,
		options: {
			attributes: modifierKeys?.attr ?? defaultValue,
			characterData: modifierKeys?.char ?? defaultValue,
			childList: modifierKeys?.child ?? defaultValue,
			subtree: modifierKeys?.sub ?? defaultValue
		}
	};
	const observer = new MutationObserver(function() {
		let mutations = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : [];
		let observer = arguments.length > 1 ? arguments[1] : void 0;
		handler?.(mutations, observer);
		if (once) unmounted$2(el, binding);
	});
	if (immediate) handler?.([], observer);
	el._mutate = Object(el._mutate);
	el._mutate[binding.instance.$.uid] = { observer };
	observer.observe(el, options);
}
function unmounted$2(el, binding) {
	if (!el._mutate?.[binding.instance.$.uid]) return;
	el._mutate[binding.instance.$.uid].observer.disconnect();
	delete el._mutate[binding.instance.$.uid];
}
var Mutate = {
	mounted: mounted$2,
	unmounted: unmounted$2
};
//#endregion
//#region node_modules/vuetify/lib/directives/resize/index.js
function mounted$1(el, binding) {
	const handler = binding.value;
	const options = { passive: !binding.modifiers?.active };
	window.addEventListener("resize", handler, options);
	el._onResize = Object(el._onResize);
	el._onResize[binding.instance.$.uid] = {
		handler,
		options
	};
	if (!binding.modifiers?.quiet) handler();
}
function unmounted$1(el, binding) {
	if (!el._onResize?.[binding.instance.$.uid]) return;
	const { handler, options } = el._onResize[binding.instance.$.uid];
	window.removeEventListener("resize", handler, options);
	delete el._onResize[binding.instance.$.uid];
}
var Resize = {
	mounted: mounted$1,
	unmounted: unmounted$1
};
//#endregion
//#region node_modules/vuetify/lib/directives/scroll/index.js
function mounted(el, binding) {
	const { self = false } = binding.modifiers ?? {};
	const value = binding.value;
	const options = typeof value === "object" && value.options || { passive: true };
	const handler = typeof value === "function" || "handleEvent" in value ? value : value.handler;
	const target = self ? el : binding.arg ? document.querySelector(binding.arg) : window;
	if (!target) return;
	target.addEventListener("scroll", handler, options);
	el._onScroll = Object(el._onScroll);
	el._onScroll[binding.instance.$.uid] = {
		handler,
		options,
		target: self ? void 0 : target
	};
}
function unmounted(el, binding) {
	if (!el._onScroll?.[binding.instance.$.uid]) return;
	const { handler, options, target = el } = el._onScroll[binding.instance.$.uid];
	target.removeEventListener("scroll", handler, options);
	delete el._onScroll[binding.instance.$.uid];
}
function updated(el, binding) {
	if (binding.value === binding.oldValue) return;
	unmounted(el, binding);
	mounted(el, binding);
}
var Scroll = {
	mounted,
	unmounted,
	updated
};
//#endregion
//#region node_modules/vuetify/lib/composables/directiveComponent.js
function useDirectiveComponent(component, props) {
	const hook = mountComponent(typeof component === "string" ? resolveComponent(component) : component, props);
	return {
		mounted: hook,
		updated: hook,
		unmounted(el) {
			render(null, el);
		}
	};
}
function mountComponent(component, props) {
	return function(el, binding, vnode) {
		const _props = typeof props === "function" ? props(binding) : props;
		const text = binding.value?.text ?? binding.value ?? _props?.text;
		const value = isObject(binding.value) ? binding.value : {};
		const children = () => text ?? el.textContent;
		const provides = (vnode.ctx === binding.instance.$ ? findComponentParent(vnode, binding.instance.$)?.provides : vnode.ctx?.provides) ?? binding.instance.$.provides;
		const node = h(component, mergeProps(_props, value), children);
		node.appContext = Object.assign(Object.create(null), binding.instance.$.appContext, { provides });
		render(node, el);
	};
}
function findComponentParent(vnode, root) {
	const stack = /* @__PURE__ */ new Set();
	const walk = (children) => {
		for (const child of children) {
			if (!child) continue;
			if (child === vnode || child.el && vnode.el && child.el === vnode.el) return true;
			stack.add(child);
			let result;
			if (child.suspense) result = walk([child.ssContent]);
			else if (Array.isArray(child.children)) result = walk(child.children);
			else if (child.component?.vnode) result = walk([child.component?.subTree]);
			if (result) return result;
			stack.delete(child);
		}
		return false;
	};
	if (!walk([root.subTree])) {
		consoleError("Could not find original vnode, component will not inherit provides");
		return root;
	}
	const result = Array.from(stack).reverse();
	for (const child of result) if (child.component) return child.component;
	return root;
}
//#endregion
//#region node_modules/vuetify/lib/directives/tooltip/index.js
var Tooltip = useDirectiveComponent(VTooltip, (binding) => {
	return {
		activator: "parent",
		location: binding.arg?.replace("-", " "),
		text: typeof binding.value === "boolean" ? void 0 : binding.value
	};
});
//#endregion
//#region node_modules/vuetify/lib/directives/index.js
var directives_exports = /* @__PURE__ */ __exportAll({
	ClickOutside: () => ClickOutside,
	Intersect: () => Intersect,
	Mutate: () => Mutate,
	Resize: () => Resize,
	Ripple: () => Ripple,
	Scroll: () => Scroll,
	Tooltip: () => Tooltip,
	Touch: () => Touch
});
//#endregion
//#region resources/js/ssr.js
var appName = "PISCHEPROM-SERVER";
var renderPage = (page) => createInertiaApp({
	page,
	render: renderToString,
	title: (title) => title ? `${title}` : appName,
	resolve: (name) => {
		return resolvePageComponent(`./Pages/${name}.vue`, /* @__PURE__ */ Object.assign({
			"./Pages/API/Index.vue": () => import("./assets/Index-xIjXm_gR.js"),
			"./Pages/API/Partials/ApiTokenManager.vue": () => import("./assets/ApiTokenManager-DJ0-PUXn.js"),
			"./Pages/Ameise/Avito.vue": () => import("./assets/Avito-D07-2nvi.js"),
			"./Pages/Ameise/Botany.vue": () => import("./assets/Botany-C_CQxTRs.js"),
			"./Pages/Ameise/Check.vue": () => import("./assets/Check-DbwLa1IG.js"),
			"./Pages/Ameise/Checks.vue": () => import("./assets/Checks-DCLt4OQ5.js"),
			"./Pages/Ameise/Cities.vue": () => import("./assets/Cities-Cz2MTzxx.js"),
			"./Pages/Ameise/City.vue": () => import("./assets/City-BnBDLk4p.js"),
			"./Pages/Ameise/Commodities.vue": () => import("./assets/Commodities-WNMilFUi.js"),
			"./Pages/Ameise/Commodity.vue": () => import("./assets/Commodity-QQ_cXABS.js"),
			"./Pages/Ameise/ContactsCentre.vue": () => import("./assets/ContactsCentre-DNjCIpgw.js"),
			"./Pages/Ameise/Entities.vue": () => import("./assets/Entities-JoNIP4ln.js"),
			"./Pages/Ameise/FluxMonitor.vue": () => import("./assets/FluxMonitor-BvgDwopJ.js"),
			"./Pages/Ameise/Geography.vue": () => import("./assets/Geography-2L7ygqE9.js"),
			"./Pages/Ameise/Good.vue": () => import("./assets/Good-P3o7QYsb.js"),
			"./Pages/Ameise/Goods.vue": () => import("./assets/Goods-Ci0azrOE.js"),
			"./Pages/Ameise/Grossbuch.vue": () => import("./assets/Grossbuch-DMrivCiu.js"),
			"./Pages/Ameise/Perfume.vue": () => import("./assets/Perfume-BgS1uJfK.js"),
			"./Pages/Ameise/Product.vue": () => import("./assets/Product-C57FayuB.js"),
			"./Pages/Ameise/Product_02.vue": () => import("./assets/Product_02-CuqEmNO8.js"),
			"./Pages/Ameise/Products.vue": () => import("./assets/Products-Bl5fRWaC.js"),
			"./Pages/Ameise/Region.vue": () => import("./assets/Region-DHwDo7AV.js"),
			"./Pages/Ameise/Regions.vue": () => import("./assets/Regions-B7Ok2NAC.js"),
			"./Pages/Ameise/Sales.vue": () => import("./assets/Sales-CX2p3Gbj.js"),
			"./Pages/Ameise/TelegramBot.vue": () => import("./assets/TelegramBot-DSqua_MX.js"),
			"./Pages/Ameise/Unit.vue": () => import("./assets/Unit-DeYaZFSb.js"),
			"./Pages/Ameise/Units.vue": () => import("./assets/Units-DK0qG9rv.js"),
			"./Pages/Ameise/Verwalter.vue": () => import("./assets/Verwalter-BzyfP0S4.js"),
			"./Pages/Ameise/WorkBoard.vue": () => import("./assets/WorkBoard-C8DSdvmu.js"),
			"./Pages/Ameise/Yandex.vue": () => import("./assets/Yandex-BPYui69k.js"),
			"./Pages/Auth/ConfirmPassword.vue": () => import("./assets/ConfirmPassword-Mz4B5mzL.js"),
			"./Pages/Auth/ForgotPassword.vue": () => import("./assets/ForgotPassword-SIsjpSsO.js"),
			"./Pages/Auth/Login.vue": () => import("./assets/Login-YtafiQIo.js"),
			"./Pages/Auth/Register.vue": () => import("./assets/Register-CClgr5Tk.js"),
			"./Pages/Auth/ResetPassword.vue": () => import("./assets/ResetPassword-DMESHrRN.js"),
			"./Pages/Auth/TwoFactorChallenge.vue": () => import("./assets/TwoFactorChallenge-CbI5T8oZ.js"),
			"./Pages/Auth/VerifyEmail.vue": () => import("./assets/VerifyEmail-D_g1N4Ye.js"),
			"./Pages/Categories/Show.vue": () => import("./assets/Show-CkEwhbPL.js"),
			"./Pages/Categories.vue": () => import("./assets/Categories-6Gs_Txwi.js"),
			"./Pages/Dashboard.vue": () => import("./assets/Dashboard-BGHE4E-u.js"),
			"./Pages/Glycerol.vue": () => import("./assets/Glycerol-CpgHXohz.js"),
			"./Pages/Goods/Show.vue": () => import("./assets/Show-DV03C9gT.js"),
			"./Pages/Goods.vue": () => import("./assets/Goods-CjqmagkI.js"),
			"./Pages/GribyBeliyKubik.vue": () => import("./assets/GribyBeliyKubik-BZOmyzfJ.js"),
			"./Pages/PrivacyPolicy.vue": () => import("./assets/PrivacyPolicy-BkkhlrNB.js"),
			"./Pages/Products/Show.vue": () => import("./assets/Show-V0IlwsPs.js"),
			"./Pages/Profile/Partials/DeleteUserForm.vue": () => import("./assets/DeleteUserForm-CksZf2ie.js"),
			"./Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue": () => import("./assets/LogoutOtherBrowserSessionsForm-D8kyiEIK.js"),
			"./Pages/Profile/Partials/TwoFactorAuthenticationForm.vue": () => import("./assets/TwoFactorAuthenticationForm-B4oEkEiu.js"),
			"./Pages/Profile/Partials/UpdatePasswordForm.vue": () => import("./assets/UpdatePasswordForm-BrHurooS.js"),
			"./Pages/Profile/Partials/UpdateProfileInformationForm.vue": () => import("./assets/UpdateProfileInformationForm-B7C5hZIB.js"),
			"./Pages/Profile/Show.vue": () => import("./assets/Show-DMt8BikA.js"),
			"./Pages/Purchases/Purchases.vue": () => import("./assets/Purchases-BwA6yP9D.js"),
			"./Pages/Seaprom.vue": () => import("./assets/Seaprom-qT9hpxav.js"),
			"./Pages/Sesame.vue": () => import("./assets/Sesame-DUJhqTIp.js"),
			"./Pages/TermsOfService.vue": () => import("./assets/TermsOfService-CelYdFjv.js"),
			"./Pages/Welcome.vue": () => import("./assets/Welcome-Ho9rE4XK.js")
		}));
	},
	setup({ App, props, plugin }) {
		const app = createSSRApp({ render: () => h(App, props) });
		const vuetify = createVuetify({
			components: components_exports,
			directives: directives_exports
		});
		app.use(plugin);
		app.use(vuetify);
		if (page.props.ziggy) app.use(ZiggyVue, {
			...page.props.ziggy,
			location: new URL(page.props.ziggy.location)
		});
		else app.use(ZiggyVue);
		return app;
	}
});
createServer(renderPage);
//#endregion
export { VAvatar as $, VCombobox as A, VBadge as B, VRow as C, VDataTable as D, VDataTableServer as E, VCardText as F, VList as G, VSelect as H, VCardTitle as I, VListItemTitle as J, VDivider as K, VCardSubtitle as L, VWindowItem as M, VWindow as N, VTable as O, VCard as P, VLabel as Q, VCardActions as R, VSpacer as S, VContainer as T, VTextField as U, VAutocomplete as V, VMenu as W, VChip as X, VListItemSubtitle as Y, VCheckbox as Z, VExpansionPanels as _, VTabs as a, VProgressCircular as at, VExpansionPanelText as b, VTab as c, VToolbar as ct, VSkeletonLoader as d, renderPage as default, useDate as dt, VAlert as et, VNavigationDrawer as f, VFileInput as g, VForm as h, VTextarea as i, VProgressLinear as it, VSheet as j, VPagination as k, VSwitch as l, VImg as lt, VLayout as m, VToolbarItems as n, VAppBarNavIcon as nt, VTabsWindowItem as o, VIcon as ot, VMain as p, VListItem as q, VThemeProvider as r, VBtn as rt, VTabsWindow as s, VAppBar as st, VTooltip as t, VAppBarTitle as tt, VSnackbar as u, VExpandTransition as ut, VExpansionPanel as v, VCol as w, VDatePicker as x, VExpansionPanelTitle as y, VDialog as z };

//# sourceMappingURL=ssr.js.map