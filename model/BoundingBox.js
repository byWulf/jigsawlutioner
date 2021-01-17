class BoundingBox {
    /**
     * @type {number}
     */
    left;

    /**
     * @type {number}
     */
    right;

    /**
     * @type {number}
     */
    top;

    /**
     * @type {number}
     */
    bottom;

    constructor(left, right, top, bottom) {
        this.left = left;
        this.right = right;
        this.top = top;
        this.bottom = bottom;
    }
}
