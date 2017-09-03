const MathHelper = require('./mathHelper');
const PathHelper = require('./pathHelper');
const Cache = require('./cache');

/**
 * Returns some values to determine, how good the given sides match up.
 *
 * avgDistance: Average distance of the lines
 * worstSingleDistance: Maximum distance between the two lines
 * offsetX: How much did the pieces have to be moved for the best outcome.
 * offsetY: How much did the pieces have to be moved for the best outcome.
 *
 * @param sourceSide
 * @param targetSide
 * @param thresholdX
 * @param thresholdY
 * @returns {*}
 */
function getSideMatchingFactor(sourceSide, targetSide, thresholdX, thresholdY) {
    if (typeof thresholdX === 'undefined') {
        thresholdX = 0;
    }
    if (typeof thresholdY === 'undefined') {
        thresholdY = 0;
    }

    //Caching to reduce calculation time
    if (Cache.has(['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, targetSide.pieceIndex, targetSide.sideIndex, thresholdX, thresholdY])) {
        return Cache.get(['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, targetSide.pieceIndex, targetSide.sideIndex, thresholdX, thresholdY]);
    }

    let result = {
        pieceIndex: targetSide.pieceIndex,
        sideIndex: targetSide.sideIndex,
        matches: false,
        deviation: 100,
        avgDistance: null,
        worstSingleDistance: null,
        offsetX: null,
        offsetY: null,
        sameSide: sourceSide.direction === targetSide.direction,
        directLengthDiff: Math.abs(targetSide.directLength - sourceSide.directLength),
        areaDiff: Math.abs(targetSide.area + sourceSide.area),
        smallNopDiff: Math.abs(Math.abs(targetSide.nop.min.right - targetSide.nop.min.left) - Math.abs(sourceSide.nop.min.right - sourceSide.nop.min.left)),
        bigNopDiff: Math.abs(Math.abs(targetSide.nop.max.right - targetSide.nop.max.left) - Math.abs(sourceSide.nop.max.right - sourceSide.nop.max.left)),
        nopHeightDiff: Math.abs(targetSide.nop.height + sourceSide.nop.height),
        nopCenterDiff: Math.abs((targetSide.nop.max.left + (targetSide.nop.max.right - targetSide.nop.max.left) / 2) + (sourceSide.nop.max.left + (sourceSide.nop.max.right - sourceSide.nop.max.left) / 2)),
    };

    let detailedCheck = !result.sameSide && result.smallNopDiff <= 12 && result.bigNopDiff <= 12 && result.nopCenterDiff <= 12 && result.nopHeightDiff <= 12;

    if (detailedCheck) {
        //Check form of the sides
        for (let offsetY = -thresholdY; offsetY <= thresholdY; offsetY += Math.max(1, thresholdY / 3)) {
            for (let offsetX = -thresholdX; offsetX <= thresholdX; offsetX += Math.max(1, thresholdX / 3)) {
                let distances = MathHelper.distancesOfPolylines(PathHelper.rotatePoints(sourceSide.points), targetSide.points, offsetX, offsetY);

                if (result.avgDistance === null || distances.avgDistance < result.avgDistance) {
                    result.avgDistance = distances.avgDistance;
                    result.worstSingleDistance = distances.maxDistance;
                    result.offsetX = offsetX;
                    result.offsetY = offsetY;
                }
            }
        }
    }

    let sum = Math.round(
       result.avgDistance +
       result.directLengthDiff +
       result.worstSingleDistance +
       result.nopCenterDiff +
       result.nopHeightDiff +
       result.smallNopDiff +
       result.bigNopDiff
    );
    result.deviation = sum / 100;

    if (detailedCheck && sum <= 100) {
        result.matches = true;
    }

    Cache.set(['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, targetSide.pieceIndex, targetSide.sideIndex, thresholdX, thresholdY], result);
    return result;
}

/**
 * Returns the pieces, who could match on any side with the given piece.
 *
 * @param piece
 * @param pieces
 * @param onlySide (optional)
 */
function findMatchingPieces(piece, pieces, onlySide) {
    Cache.clear();

    let matches = {};
    for (let sideIndex = 0; sideIndex < piece.sides.length; sideIndex++) {
        if (typeof onlySide === 'number' && sideIndex !== onlySide) continue;

        let bestDeviation = null;
        let results = [];

        if (piece.sides[sideIndex].direction !== 'straight') {
            for (let comparePiece of pieces) {
                if (comparePiece.pieceIndex === piece.pieceIndex) continue;

                for (let compareSideIndex = 0; compareSideIndex < comparePiece.sides.length; compareSideIndex++) {

                    let match = getSideMatchingFactor(piece.sides[sideIndex], comparePiece.sides[compareSideIndex]);

                    if (match.matches) {
                        match.piece = comparePiece;
                        results.push(match);
                        if (bestDeviation === null || match.deviation < bestDeviation) {
                            bestDeviation = match.deviation;
                        }
                    }
                }
            }
        }

        matches[sideIndex] = [];
        for (let i = 0; i < results.length; i++) {
            if (results[i].deviation <= bestDeviation + 0.05) {
                matches[sideIndex].push(results[i]);
            }
        }
    }

    return matches;
}

let sideOpposites = {
    0: {x: 0, y: -1},
    1: {x: -1, y: 0},
    2: {x: 0, y: 1},
    3: {x: 1, y: 0}
};

function getPlacements(pieces) {
    let remainingPieces = pieces.slice(0);
    let groups = [];

    while (remainingPieces.length > 0) {
        let placed = false;

        if (groups.length > 0) {
            //Check every free side of the current group for matching pieces
            let group = groups[groups.length - 1];

            let allMatches = [];
            for (let x in group) {
                if (!group.hasOwnProperty(x)) continue;
                x = parseInt(x, 10);

                for (let y in group[x]) {
                    if (!group[x].hasOwnProperty(y)) continue;
                    y = parseInt(y, 10);

                    let piece = group[x][y];
                    for (let realSide = 0; realSide < 4; realSide++) {
                        if  (typeof group[x + sideOpposites[realSide].x] !== 'undefined' && typeof group[x + sideOpposites[realSide].x][y + sideOpposites[realSide].y] !== 'undefined') continue;

                        let pieceSide = (realSide - piece.rotation + 4) % 4;
                        if (piece.direction !== 'straight') {
                            let matches = findMatchingPieces(piece, remainingPieces, pieceSide);
                            if (matches[pieceSide].length === 1) {
                                allMatches.push({
                                    deviation: matches[pieceSide][0].deviation,
                                    x: x + sideOpposites[realSide].x,
                                    y: y + sideOpposites[realSide].y,
                                    piece: matches[pieceSide][0].piece,
                                    rotation: (realSide - matches[pieceSide][0].sideIndex + 6) % 4
                                });
                            }
                        }
                    }
                }
            }

            //Find the best matching piece for the current group and place it
            if (allMatches.length > 0) {
                allMatches.sort((a, b) => {
                    return a.deviation - b.deviation;
                });

                let match = allMatches[0];

                if (typeof group[match.x] === 'undefined') group[match.x] = {};
                group[match.x][match.y] = match.piece;
                group[match.x][match.y].rotation = match.rotation;

                remainingPieces.splice(remainingPieces.indexOf(match.piece), 1);
                placed = true;
            }
        }

        //Nothing places, so nothing unique matching found - creating new group
        if (!placed) {
            let piece = remainingPieces.splice(0, 1)[0];

            let group = {};
            group[0] = {};
            group[0][0] = piece;
            group[0][0].rotation = 0;

            groups.push(group);
        }
    }

    return groups;
}

module.exports = {
    findMatchingPieces: findMatchingPieces,
    getSideMatchingFactor: getSideMatchingFactor,
    getPlacements: getPlacements
};