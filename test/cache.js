const assert = require('assert');

const Cache = require('../src/cache');

describe('Cache', function() {
    it('should work correctly', function() {
        assert.equal(Cache.has({foo: 'test'}), false);
        assert.equal(Cache.get({foo: 'test'}), undefined);
        assert.equal(Cache.has('test2'), false);
        assert.equal(Cache.get('test2'), undefined);

        Cache.set({foo: 'test'}, 'foobar');

        assert.equal(Cache.has({foo: 'test'}), true);
        assert.equal(Cache.get({foo: 'test'}), 'foobar');
        assert.equal(Cache.has('test2'), false);
        assert.equal(Cache.get('test2'), undefined);

        Cache.clear();

        assert.equal(Cache.has({foo: 'test'}), false);
        assert.equal(Cache.get({foo: 'test'}), undefined);
        assert.equal(Cache.has('test2'), false);
        assert.equal(Cache.get('test2'), undefined);
    });
});