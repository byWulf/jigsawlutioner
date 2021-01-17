class Side {
    /**
     * @type {Point[]}
     */
    points;

    /**
     * @type {string} One of: straight, out, in
     */
    direction;

    /**
     * @type {number}
     */
    area;

    /**
     * @type {number}
     */
    directLength;

    /**
     * @type {Point}
     */
    startPoint;

    /**
     * @type {Point}
     */
    endPoint;

    /**
     * @type {NopData}
     */
    nop;

    /**
     * @type {number}
     */
    pieceIndex;

    /**
     * @type {number}
     */
    sideIndex;

    /**
     * @type {number}
     */
    fromOffset;

    /**
     * @type {number}
     */
    toOffset;

    constructor(points, direction, area, directLength, startPoint, endPoint, nop) {
        this.points = points;
        this.direction = direction;
        this.area = area;
        this.directLength = directLength;
        this.startPoint = startPoint;
        this.endPoint = endPoint;
        this.nop = nop;
    }
}
