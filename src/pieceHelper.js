const Piece = require('../model/Piece');
const Point = require('../model/Point');

/**
 * @param borderData
 * @param sideData
 * @returns {Piece}
 */
function getLimitedPiece(borderData, sideData) {
    // Round points to 2 decimals for less data
    const sides = sideData.sides;
    for (let sideIndex = 0; sideIndex < sides.length; sideIndex++) {
        for (let i = 0; i < sides[sideIndex].points.length; i++) {
            let x = Math.round(sides[sideIndex].points[i].x * 100) / 100;
            let y = Math.round(sides[sideIndex].points[i].y * 100) / 100;

            sides[sideIndex].points[i] = new Point(x, y);
        }
    }

    return new Piece(
        sideData.pieceIndex,
        sides,
        borderData.boundingBox,
        borderData.dimensions,
        borderData.images
    );
}

module.exports = {
    getLimitedPiece: getLimitedPiece
};
