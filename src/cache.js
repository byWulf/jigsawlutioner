function Cache() {
    let cache = {};

    this.get = (key) => {
        return cache[JSON.stringify(key)];
    }

    this.has = (key) => {
        return typeof cache[JSON.stringify(key)] !== 'undefined';
    }

    this.set = (key, value) => {
        cache[JSON.stringify(key)] = value;
    }

    this.clear = () => {
        cache = {};
    }
}

module.exports = Cache;