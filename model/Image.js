class Image {
    /**
     * @type {number}
     */
    resizeFactor;

    /**
     * @type {string}
     */
    encoding;

    /**
     * @type {string}
     */
    buffer;

    constructor(resizeFactor, encoding, buffer) {
        this.resizeFactor = resizeFactor;
        this.encoding = encoding;
        this.buffer = buffer;
    }
}

module.exports = Image;
