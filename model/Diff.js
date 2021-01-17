const Paper = require('paper-jsdom');

class Diff {
    /**
     * @type {number}
     */
    offset;

    /**
     * @type {number}
     */
    diff;

    /**
     * @type {number}
     */
    deg;

    /**
     * @type {Paper.Point}
     */
    point;

    constructor(offset, diff, deg, point) {
        this.offset = offset;
        this.diff = diff;
        this.deg = deg;
        this.point = point;
    }
}

module.exports = Diff;
