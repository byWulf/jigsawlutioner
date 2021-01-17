class NopData {
    /**
     * @type {Distance}
     */
    max;

    /**
     * @type {Distance}
     */
    min;

    /**
     * @type {number}
     */
    height;

    constructor(max, min, height) {
        this.max = max;
        this.min = min;
        this.height = height;
    }
}

module.exports = NopData;
