let cache = {};

function get(key) {
    return cache[JSON.stringify(key)];
}

function has(key) {
    return typeof cache[JSON.stringify(key)] !== 'undefined';
}

function set(key, value) {
    cache[JSON.stringify(key)] = value;
}
function clear() {
    cache = {};
}

module.exports = {
    get: get,
    has: has,
    set: set,
    clear: clear
};