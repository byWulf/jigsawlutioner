const MathHelper = require('./mathHelper');
const PathHelper = require('./pathHelper');
const Cache = require('./cache');
const colors = require('colors/safe');

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
 * @param dontRotateTargetSide
 * @returns {*}
 */
function getSideMatchingFactor(sourceSide, targetSide, thresholdX, thresholdY, dontRotateTargetSide) {
    if (typeof thresholdX === 'undefined') {
        thresholdX = 0;
    }
    if (typeof thresholdY === 'undefined') {
        thresholdY = 0;
    }

    //Caching to reduce calculation time
    let cacheKey = ['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, targetSide.pieceIndex, targetSide.sideIndex, thresholdX, thresholdY, dontRotateTargetSide];
    if (Cache.has(cacheKey)) {
        return Cache.get(cacheKey);
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
        nopHeightDiff: Math.abs(targetSide.nop.height + sourceSide.nop.height * (dontRotateTargetSide ? -1 : 1)),
        nopCenterDiff: Math.abs((targetSide.nop.max.left + (targetSide.nop.max.right - targetSide.nop.max.left) / 2) + (sourceSide.nop.max.left + (sourceSide.nop.max.right - sourceSide.nop.max.left) / 2) * (dontRotateTargetSide ? -1 : 1)),
    };

    let detailedCheck = (dontRotateTargetSide || (sourceSide.direction !== 'straight' && targetSide.direction !== 'straight')) && (dontRotateTargetSide ? result.sameSide : !result.sameSide) && result.smallNopDiff <= 17 && result.bigNopDiff <= 17 && result.nopCenterDiff <= 17 && result.nopHeightDiff <= 17;


    if (detailedCheck) {
        //Check form of the sides
        for (let offsetY = -thresholdY; offsetY <= thresholdY; offsetY += Math.max(1, thresholdY / 3)) {
            for (let offsetX = -thresholdX; offsetX <= thresholdX; offsetX += Math.max(1, thresholdX / 3)) {
                let distances = MathHelper.distancesOfPolylines(dontRotateTargetSide ? sourceSide.points : PathHelper.rotatePoints(sourceSide.points), targetSide.points, offsetX, offsetY);

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

    Cache.set(cacheKey, result);
    return result;
}

/**
 * @param pieces
 * @param piece
 * @returns {null|object} {pieceIndex: number, deviation, number, sideOffset: number}
 */
function findExistingPieceIndex(pieces, piece) {
    let pieceMatchings = [];
    for (let i = 0; i < pieces.length; i++) {
        let bestMatchingFactor = null;
        sideOffsetLoop: for (let sideOffset = 0; sideOffset < 4; sideOffset++) {
            //First check if the side directions match (better performance with 2 loops)
            for (let side = 0; side < 4; side++) {
                if (piece.sides[side].direction !== pieces[i].sides[(side + sideOffset) % 4].direction) continue sideOffsetLoop;
            }
            //If all 4 sides have the same direction, then get their matching factor and remember
            let matchinFactorSum = 0;
            for (let side = 0; side < 4; side++) {
                if (piece.sides[side].direction === 'straight') continue;
                let match = getSideMatchingFactor(piece.sides[side], pieces[i].sides[(side + sideOffset) % 4], 0, 0, true);
                if (!match.matches) {
                    continue sideOffsetLoop;
                } else {
                    matchinFactorSum += match.deviation;
                }
            }
            if (bestMatchingFactor === null || matchinFactorSum < bestMatchingFactor.deviation) {
                bestMatchingFactor = {deviation: matchinFactorSum, sideOffset: sideOffset};
            }
        }

        if (bestMatchingFactor !== null) {
            pieceMatchings.push({
                pieceIndex: pieces[i].pieceIndex,
                deviation: bestMatchingFactor.deviation,
                sideOffset: bestMatchingFactor.sideOffset
            });
        }
    }

    if (pieceMatchings.length === 0) {
        return null;
    }

    pieceMatchings.sort((a, b) => {
        return a.deviation - b.deviation;
    });

    return pieceMatchings[0];
}

/**
 * Returns the pieces, who could match on any side with the given piece.
 *
 * @param piece
 * @param pieces
 * @param onlySide (optional)
 * @param factors (optional)
 */
function findMatchingPieces(piece, pieces, onlySide, factors) {
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

                    let match = null;
                    if (typeof factors !== 'undefined') {
                        match = factors[getFactorMapKey(piece.pieceIndex, sideIndex, comparePiece.pieceIndex, compareSideIndex)];
                    } else {
                        match = getSideMatchingFactor(piece.sides[sideIndex], comparePiece.sides[compareSideIndex]);
                    }

                    if (match && match.matches) {
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

function generateFactorsMap(pieces, onUpdateCallback) {
    let map = {};

    let sum = 0;
    for (let i1 = 0; i1 < pieces.length; i1++) {
        let piece = pieces[i1];
        for (let i2 = i1 + 1; i2 < pieces.length; i2++) {
            let comparePiece = pieces[i2];
            for (let sideIndex = 0; sideIndex < piece.sides.length; sideIndex++) {
                for (let compareSideIndex = 0; compareSideIndex < comparePiece.sides.length; compareSideIndex++) {
                    sum++;
                }
            }
        }
    }

    let done = 0;
    for (let i1 = 0; i1 < pieces.length; i1++) {
        let piece = pieces[i1];
        for (let i2 = i1 + 1; i2 < pieces.length; i2++) {
            let comparePiece = pieces[i2];
            for (let sideIndex = 0; sideIndex < piece.sides.length; sideIndex++) {
                for (let compareSideIndex = 0; compareSideIndex < comparePiece.sides.length; compareSideIndex++) {
                    map[getFactorMapKey(piece.pieceIndex, sideIndex, comparePiece.pieceIndex, compareSideIndex)] = getSideMatchingFactor(piece.sides[sideIndex], comparePiece.sides[compareSideIndex]);

                    done++;

                    if (typeof onUpdateCallback === 'function') {
                        onUpdateCallback(done, sum);
                    }
                }
            }
        }
    }

    return map;
}

function getFactorMapKey(pieceIndex1, sideIndex1, pieceIndex2, sideIndex2) {
    if (parseInt(pieceIndex1) < parseInt(pieceIndex2)) {
        return pieceIndex1 + '_' + sideIndex1 + '_' + pieceIndex2 + '_' + sideIndex2;
    } else {
        return pieceIndex2 + '_' + sideIndex2 + '_' + pieceIndex1 + '_' + sideIndex1;
    }
}

let sideOpposites = {
    0: {x: 0, y: -1},
    1: {x: -1, y: 0},
    2: {x: 0, y: 1},
    3: {x: 1, y: 0}
};

function getPlacements(pieces, factorMap, options, onUpdateCallback) {
    let baseOptions = {
        avgDistanceFactor: 4.725,
        directLengthDiffFactor: 0.462,
        worstSingleDistanceFactor: 2,
        nopCenterDiffFactor: 0.76,
        nopHeightDiffFactor: 1.134,
        smallNopDiffFactor: 0.945,
        bigNopDiffFactor: 1.26,
        moreSidesBetterFactor: 1.575,
        noDistinctionLimit: 0.085,
        avgDistanceOffset: -2,
        directLengthDiffOffset: 0,
        bigNopDiffOffset: -1,
        smallNopDiffOffset: -2,
        avgDistancePow: 1,
        directLengthDiffPow: 1.01,
        worstSingleDistancePow: 0.99,
        nopCenterDiffPow: 1,
        nopHeightDiffPow: 1,
        smallNopDiffPow: 1,
        bigNopDiffPow: 1
    };
    if (!options) options = {};
    if (typeof options.avgDistanceFactor === 'undefined') options.avgDistanceFactor = baseOptions.avgDistanceFactor;
    if (typeof options.avgDistanceOffset === 'undefined') options.avgDistanceOffset = baseOptions.avgDistanceOffset;
    if (typeof options.directLengthDiffFactor === 'undefined') options.directLengthDiffFactor = baseOptions.directLengthDiffFactor;
    if (typeof options.directLengthDiffOffset === 'undefined') options.directLengthDiffOffset = baseOptions.directLengthDiffOffset;
    if (typeof options.worstSingleDistanceFactor === 'undefined') options.worstSingleDistanceFactor = baseOptions.worstSingleDistanceFactor;
    if (typeof options.nopCenterDiffFactor === 'undefined') options.nopCenterDiffFactor = baseOptions.nopCenterDiffFactor;
    if (typeof options.nopHeightDiffFactor === 'undefined') options.nopHeightDiffFactor = baseOptions.nopHeightDiffFactor;
    if (typeof options.smallNopDiffFactor === 'undefined') options.smallNopDiffFactor = baseOptions.smallNopDiffFactor;
    if (typeof options.smallNopDiffOffset === 'undefined') options.smallNopDiffOffset = baseOptions.smallNopDiffOffset;
    if (typeof options.bigNopDiffFactor === 'undefined') options.bigNopDiffFactor = baseOptions.bigNopDiffFactor;
    if (typeof options.bigNopDiffOffset === 'undefined') options.bigNopDiffOffset = baseOptions.bigNopDiffOffset;

    if (typeof options.moreSidesBetterFactor === 'undefined') options.moreSidesBetterFactor = baseOptions.moreSidesBetterFactor;
    if (typeof options.noDistinctionLimit === 'undefined') options.noDistinctionLimit = baseOptions.noDistinctionLimit;

    if (typeof options.avgDistancePow === 'undefined') options.avgDistancePow = baseOptions.avgDistancePow;
    if (typeof options.directLengthDiffPow === 'undefined') options.directLengthDiffPow = baseOptions.directLengthDiffPow;
    if (typeof options.worstSingleDistancePow === 'undefined') options.worstSingleDistancePow = baseOptions.worstSingleDistancePow;
    if (typeof options.nopCenterDiffPow === 'undefined') options.nopCenterDiffPow = baseOptions.nopCenterDiffPow;
    if (typeof options.nopHeightDiffPow === 'undefined') options.nopHeightDiffPow = baseOptions.nopHeightDiffPow;
    if (typeof options.smallNopDiffPow === 'undefined') options.smallNopDiffPow = baseOptions.smallNopDiffPow;
    if (typeof options.bigNopDiffPow === 'undefined') options.bigNopDiffPow = baseOptions.bigNopDiffPow;

    if (typeof options.ignoreMatches === 'undefined') options.ignoreMatches = [];

    let piecesSum = pieces.length;
    let remainingPieces = pieces.slice(0);
    let groups = [];

    if (!factorMap) {
        factorMap = generateFactorsMap(pieces);
    }

    let placeMatches = {};
    while (remainingPieces.length > 0) {
        if (typeof onUpdateCallback === 'function') {
            onUpdateCallback(piecesSum - remainingPieces.length, piecesSum);
        }

        let placed = false;

        if (groups.length > 0) {
            //Check every free side of the current group for matching pieces
            let group = groups[groups.length - 1];

            let places = getFreePlaces(group);
            placeMatches = {};
            for (let i = 0; i < places.length; i++) {
                if (1 || typeof placeMatches[places[i].x] === 'undefined' || typeof placeMatches[places[i].x][places[i].y] === 'undefined') {
                    if (typeof placeMatches[places[i].x] === 'undefined') placeMatches[places[i].x] = {};
                    placeMatches[places[i].x][places[i].y] = [];
                    for (let p = 0; p < remainingPieces.length; p++) {
                        //Try to rotate the remaining piece to every direction to this slot
                        pieceRotationLoop: for (let rotation = 0; rotation < 4; rotation++) {

                            for (let side = 0; side < 4; side++) {
                                if (places[i].sideLimitations[side] !== null) {
                                    if (typeof remainingPieces[p].sides[(side - rotation + 4) % 4] !== 'undefined') {
                                        let sideDirection = remainingPieces[p].sides[(side - rotation + 4) % 4].direction;

                                        if (places[i].sideLimitations[side] === 'straight' && sideDirection !== 'straight') {
                                            continue pieceRotationLoop;
                                        }
                                        if (places[i].sideLimitations[side] === 'notStraight' && sideDirection === 'straight') {
                                            continue pieceRotationLoop;
                                        }
                                    }
                                }
                            }

                            let matchFactors = [];
                            //Now check if the connecting sides match and if they do, how good they match
                            for (let realSide = 0; realSide < 4; realSide++) {
                                let targetX = parseInt(places[i].x) + parseInt(sideOpposites[realSide].x);
                                let targetY = parseInt(places[i].y) + parseInt(sideOpposites[realSide].y);
                                if (typeof group[targetX] === 'undefined' || typeof group[targetX][targetY] === 'undefined') continue;

                                let sideIndex = (realSide - rotation + 4) % 4;

                                let oppositePiece = group[targetX][targetY];
                                let oppositeSideIndex = (realSide - (oppositePiece.rotation + 2) + 4) % 4;

                                let match = factorMap[getFactorMapKey(remainingPieces[p].pieceIndex, sideIndex, oppositePiece.pieceIndex, oppositeSideIndex)];
                                if (!match || !match.matches) {
                                    continue pieceRotationLoop;
                                }

                                for (let ii = 0; ii < options.ignoreMatches.length; ii++) {
                                    if (
                                        options.ignoreMatches[ii].source.pieceIndex === remainingPieces[p].pieceIndex &&
                                        options.ignoreMatches[ii].source.sideIndex === sideIndex &&
                                        options.ignoreMatches[ii].target.pieceIndex === oppositePiece.pieceIndex &&
                                        options.ignoreMatches[ii].target.sideIndex === oppositeSideIndex
                                    ) {
                                        continue pieceRotationLoop;
                                    }
                                }

                                let sum =
                                    options.avgDistanceFactor * Math.pow(Math.abs(match.avgDistance + options.avgDistanceOffset), options.avgDistancePow) +
                                    options.directLengthDiffFactor * Math.pow(Math.abs(match.directLengthDiff + options.directLengthDiffOffset), options.directLengthDiffPow) +
                                    options.worstSingleDistanceFactor * Math.pow(match.worstSingleDistance, options.worstSingleDistancePow) +
                                    options.nopCenterDiffFactor * Math.pow(match.nopCenterDiff, options.nopCenterDiffPow) +
                                    options.nopHeightDiffFactor * Math.pow(match.nopHeightDiff, options.nopHeightDiffPow) +
                                    options.smallNopDiffFactor * Math.pow(Math.abs(match.smallNopDiff + options.smallNopDiffOffset), options.smallNopDiffPow) +
                                    options.bigNopDiffFactor * Math.pow(Math.abs(match.bigNopDiff + options.bigNopDiffOffset), options.bigNopDiffPow);
                                match.deviation = sum / 100;

                                matchFactors.push(match);
                            }

                            //Only if all existing sides matched, this part is called
                            let avgDeviation = 0;
                            for (let m = 0; m < matchFactors.length; m++) {
                                avgDeviation += matchFactors[m].deviation / (matchFactors.length * options.moreSidesBetterFactor);
                            }
                            placeMatches[places[i].x][places[i].y].push({
                                piece: remainingPieces[p],
                                rotation: rotation,
                                avgDeviation: avgDeviation,
                                connectingSides: matchFactors.length
                            });
                        }
                    }
                }

                //Save the best fitting pieces to the place for later comparision
                placeMatches[places[i].x][places[i].y].sort((a, b) => {
                    return a.avgDeviation - b.avgDeviation
                });
                //There has to be exactly ONE "best fitting" match to be sure, that THIS is for this spot and no other may fit also
                if (placeMatches[places[i].x][places[i].y].length > 0 && (placeMatches[places[i].x][places[i].y].length === 1 || placeMatches[places[i].x][places[i].y][1].avgDeviation - placeMatches[places[i].x][places[i].y][0].avgDeviation > options.noDistinctionLimit)) {
                    places[i].match = placeMatches[places[i].x][places[i].y][0];
                }
            }

            //Check in all places, where the best fitting piece is
            places.sort((a, b) => {
                if (!a.match && !b.match) return 0;
                if (a.match && !b.match) return -1;
                if (!a.match && b.match) return 1;
                return a.match.avgDeviation - b.match.avgDeviation;
            });
            if (places[0].match) {
                if (typeof group[places[0].x] === 'undefined') group[places[0].x] = {};
                group[places[0].x][places[0].y] = places[0].match.piece;
                group[places[0].x][places[0].y].rotation = places[0].match.rotation;

                remainingPieces.splice(remainingPieces.indexOf(places[0].match.piece), 1);

                for (let x = -1; x <= 1; x++) {
                    for (let y = -1; y <= 1; y++) {
                        if (typeof placeMatches[places[0].x + x] !== 'undefined' && typeof placeMatches[places[0].x + x][places[0].y + y] !== 'undefined') {
                            delete placeMatches[places[0].x + x][places[0].y + y];
                        }
                    }
                }

                for (let x in placeMatches) {
                    if (!placeMatches.hasOwnProperty(x)) continue;

                    for (let y in placeMatches[x]) {
                        if (!placeMatches[x].hasOwnProperty(y)) continue;

                        for (let i = 0; i < placeMatches[x][y].length; i++) {
                            if (placeMatches[x][y][i].piece.pieceIndex === places[0].match.piece.pieceIndex) {
                                placeMatches[x][y].splice(i, 1);
                                break;
                            }
                        }
                    }
                }

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

            placeMatches = {};
        }
    }

    decoratePlacementsWithCorrectPositions(groups);

    return groups;
}

function decoratePlacementsWithCorrectPositions(placements) {
    let width = 0;
    let height = 0;
    let groupSizes = {};
    for (let g = 0; g < placements.length; g++) {
        let groupPlacements = placements[g];
        groupSizes[g] = {minX: 0, maxX: 0, minY: 0, maxY: 0, avgWidth: 0, avgHeight: 0, count: 0};

        for (let x in groupPlacements) {
            if (!groupPlacements.hasOwnProperty(x)) continue;

            groupSizes[g].minX = Math.min(groupSizes[g].minX, x);
            groupSizes[g].maxX = Math.max(groupSizes[g].maxX, x);

            for (let y in groupPlacements[x]) {
                if (!groupPlacements[x].hasOwnProperty(y)) continue;

                let piece = groupPlacements[x][y];
                if (typeof piece.sides === 'undefined' || !(piece.sides instanceof Array) || piece.sides.length !== 4) continue;

                groupSizes[g].minY = Math.min(groupSizes[g].minY, y);
                groupSizes[g].maxY = Math.max(groupSizes[g].maxY, y);

                groupSizes[g].count++;
                groupSizes[g].avgHeight += MathHelper.distanceOfPoints(piece.sides[(3 - piece.rotation) % 4].startPoint, piece.sides[(3 - piece.rotation) % 4].endPoint);
                groupSizes[g].avgWidth += MathHelper.distanceOfPoints(piece.sides[(3 - piece.rotation + 1) % 4].startPoint, piece.sides[(3 - piece.rotation + 1) % 4].endPoint);
                groupSizes[g].avgHeight += MathHelper.distanceOfPoints(piece.sides[(3 - piece.rotation + 2) % 4].startPoint, piece.sides[(3 - piece.rotation + 2) % 4].endPoint);
                groupSizes[g].avgWidth += MathHelper.distanceOfPoints(piece.sides[(3 - piece.rotation + 3) % 4].startPoint, piece.sides[(3 - piece.rotation + 3) % 4].endPoint);
            }
        }

        width = Math.max(width, groupSizes[g].maxX - groupSizes[g].minX + 1);
        height += groupSizes[g].maxY - groupSizes[g].minY + 1 + 2;

        groupSizes[g].avgWidth /= groupSizes[g].count * 2;
        groupSizes[g].avgHeight /= groupSizes[g].count * 2;
    }

    let currentY = 0;
    for (let g = 0; g < placements.length; g++) {
        let groupPlacements = placements[g];

        let pieceSize = groupSizes[g].avgWidth;

        for (let x in groupPlacements) {
            if (!groupPlacements.hasOwnProperty(x)) continue;

            for (let y in groupPlacements[x]) {
                if (!groupPlacements[x].hasOwnProperty(y)) continue;

                let piece = groupPlacements[x][y];
                if (typeof piece.sides === 'undefined' || !(piece.sides instanceof Array) || piece.sides.length !== 4) continue;

                let distanceXFactor = 1;
                let distanceYFactor = groupSizes[g].avgHeight / groupSizes[g].avgWidth;

                let destinationX = ((parseInt(x, 10) - groupSizes[g].minX + 1) * pieceSize) * distanceXFactor;
                let destinationY = ((/*todo: currentY + 1 + */parseInt(y, 10) - groupSizes[g].minY + 1) * pieceSize) * distanceYFactor;

                let rotation = MathHelper.getRotationOfRectangle( //TODO: Rotation of side 0 and not of the scanned puzzle.....
                    piece.sides[(3 - piece.rotation) % 4].startPoint,
                    piece.sides[(3 - piece.rotation + 1) % 4].startPoint,
                    piece.sides[(3 - piece.rotation + 2) % 4].startPoint,
                    piece.sides[(3 - piece.rotation + 3) % 4].startPoint
                );

                piece.correctPosition = {
                    x: destinationX,
                    y: destinationY,
                    rotation: rotation
                };
                console.log(piece.correctPosition);

                piece.groupSizes = groupSizes[g];
            }
        }

        currentY += groupSizes[g].maxY - groupSizes[g].minY + 2;
    }
}

function getFreePlaces(group) {
    let places = [];

    let borders = {0: null, 1: null, 2: null, 3: null};
    let limits = {0: null, 1: null, 2: null, 3: null};

    for (let x in group) {
        if (!group.hasOwnProperty(x)) continue;
        x = parseInt(x, 10);

        for (let y in group[x]) {
            if (!group[x].hasOwnProperty(y)) continue;
            y = parseInt(y, 10);

            for (let i = 0; i < 4; i++) {
                if (borders[i] === null) {
                    borders[i] = {
                        minX: x,
                        minY: y,
                        maxX: x,
                        maxY: y
                    };
                }
                if (x < borders[i].minX) borders[i].minX = x;
                if (x > borders[i].maxX) borders[i].maxX = x;
                if (y < borders[i].minY) borders[i].minY = y;
                if (y > borders[i].maxY) borders[i].maxY = y;

                if (typeof group[x][y].sides[(i - group[x][y].rotation + 4) % 4] !== 'undefined' && group[x][y].sides[(i - group[x][y].rotation + 4) % 4].direction === 'straight') {
                    if (limits[i] === null) {
                        limits[i] = {
                            minX: x,
                            minY: y,
                            maxX: x,
                            maxY: y
                        };
                    }
                    if (x < limits[i].minX) limits[i].minX = x;
                    if (x > limits[i].maxX) limits[i].maxX = x;
                    if (y < limits[i].minY) limits[i].minY = y;
                    if (y > limits[i].maxY) limits[i].maxY = y;
                }
            }
        }
    }

    for (let x in group) {
        if (!group.hasOwnProperty(x)) continue;
        x = parseInt(x, 10);

        for (let y in group[x]) {
            if (!group[x].hasOwnProperty(y)) continue;
            y = parseInt(y, 10);

            for (let realSide = 0; realSide < 4; realSide++) {
                if (typeof group[x + sideOpposites[realSide].x] !== 'undefined' && typeof group[x + sideOpposites[realSide].x][y + sideOpposites[realSide].y] !== 'undefined') continue;

                let place = {x: x + sideOpposites[realSide].x, y: y + sideOpposites[realSide].y, sideLimitations: {0: null, 1: null, 2: null, 3: null}};

                if (limits[1] !== null && place.x === limits[1].minX) place.sideLimitations[1] = 'straight';
                else if (limits[1] !== null && place.x > limits[1].minX) place.sideLimitations[1] = 'notStraight';
                else if (borders[1] !== null && place.x >= borders[1].minX) place.sideLimitations[1] = 'notStraight';

                if (limits[3] !== null && place.x === limits[3].maxX) place.sideLimitations[3] = 'straight';
                else if (limits[3] !== null && place.x < limits[3].maxX) place.sideLimitations[3] = 'notStraight';
                else if (borders[3] !== null && place.x <= borders[3].maxX) place.sideLimitations[3] = 'notStraight';

                if (limits[0] !== null && place.y === limits[0].minY) place.sideLimitations[0] = 'straight';
                else if (limits[0] !== null && place.y > limits[0].minY) place.sideLimitations[0] = 'notStraight';
                else if (borders[0] !== null && place.y >= borders[0].minY) place.sideLimitations[0] = 'notStraight';

                if (limits[2] !== null && place.y === limits[2].maxY) place.sideLimitations[2] = 'straight';
                else if (limits[2] !== null && place.y < limits[2].maxY) place.sideLimitations[2] = 'notStraight';
                else if (borders[2] !== null && place.y <= borders[2].maxY) place.sideLimitations[2] = 'notStraight';

                let alreadyAdded = false;
                for (let i = 0; i < places.length; i++) {
                    if (places[i].x === place.x && places[i].y === place.y) {
                        alreadyAdded = true;
                        break;
                    }
                }
                if (!alreadyAdded) {
                    if (limits[1] !== null && place.x < limits[1].minX) continue;
                    if (limits[3] !== null && place.x > limits[3].maxX) continue;
                    if (limits[0] !== null && place.y < limits[0].minY) continue;
                    if (limits[2] !== null && place.y > limits[2].maxY) continue;

                    places.push(place);
                }
            }
        }
    }

    return places;
}

module.exports = {
    findMatchingPieces: findMatchingPieces,
    getSideMatchingFactor: getSideMatchingFactor,
    getPlacements: getPlacements,
    findExistingPieceIndex: findExistingPieceIndex,
    generateFactorsMap: generateFactorsMap,
    getFreePlaces: getFreePlaces,
    getFactorMapKey: getFactorMapKey
};