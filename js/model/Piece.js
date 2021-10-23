class Piece {
    /**
     * @type {number}
     */
    pieceIndex;

    /**
     * @type {Side[]}
     */
    sides;

    /**
     * @type {BoundingBox}
     */
    boundingBox;

    /**
     * @type {Dimensions}
     */
    dimensions;

    /**
     * @type {Object<Image>}
     */
    images;

    constructor(pieceIndex, sides, boundingBox, dimensions, images) {
        this.pieceIndex = pieceIndex;
        this.sides = sides;
        this.boundingBox = boundingBox;
        this.dimensions = dimensions;
        this.images = images;
    }
}

module.exports = Piece;
