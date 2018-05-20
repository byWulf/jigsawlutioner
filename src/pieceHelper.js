function getLimitedPiece(borderData, sideData) {
    let limitedPiece = {
        pieceIndex: sideData.pieceIndex,
        sides: sideData.sides,
        boundingBox: borderData.boundingBox,
        dimensions: borderData.dimensions,
        images: borderData.images
    };

    for (let sideIndex = 0; sideIndex < limitedPiece.sides.length; sideIndex++) {
        for (let i = 0; i < limitedPiece.sides[sideIndex].points.length; i++) {
            let x = Math.round(limitedPiece.sides[sideIndex].points[i].x * 100) / 100;
            let y = Math.round(limitedPiece.sides[sideIndex].points[i].y * 100) / 100;

            limitedPiece.sides[sideIndex].points[i] = {
                x: x,
                y: y
            };
        }
    }

    return limitedPiece;
}

module.exports = {
    getLimitedPiece: getLimitedPiece
};