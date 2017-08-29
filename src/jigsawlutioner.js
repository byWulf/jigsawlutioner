const MathHelper = require('./mathHelper');
const PathHelper = require('./pathHelper');
const Debug = require('./debug');
const Cache = require('./cache');

let nextPieceIndex = 0;

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

    let detailedCheck = !result.sameSide && result.smallNopDiff < 10 && result.bigNopDiff < 10 && result.nopCenterDiff < 10;

    if (detailedCheck) {
        Debug.countIteration('getSideMatchFactor');

        //Check form of the sides
        for (let offsetY = -thresholdY; offsetY <= thresholdY; offsetY += Math.max(1, thresholdY / 3)) {
            for (let offsetX = -thresholdX; offsetX <= thresholdX; offsetX += Math.max(1, thresholdX / 3)) {
                Debug.countIteration('getSideMatchingFactor_distances');

                Debug.startTime('getSideMatchingFactor_distancesOfPolylines');
                let distances = MathHelper.distancesOfPolylines(PathHelper.rotatePoints(sourceSide.points), targetSide.points, offsetX, offsetY);
                Debug.endTime('getSideMatchingFactor_distancesOfPolylines');

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
 */
function findMatchingPieces(piece, pieces) {
    let matches = {};
    for (let sideIndex = 0; sideIndex < piece.sides.length; sideIndex++) {
        let bestDeviation = null;
        let results = [];

        for (let comparePiece of pieces) {
            if (comparePiece.pieceIndex === piece.pieceIndex) continue;

            for (let compareSideIndex = 0; compareSideIndex < comparePiece.sides.length; compareSideIndex++) {

                let match = getSideMatchingFactor(piece.sides[sideIndex], comparePiece.sides[compareSideIndex]);

                if (match.matches) {
                    results.push(match);
                    if (bestDeviation === null || match.deviation < bestDeviation) {
                        bestDeviation = match.deviation;
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

function getPieceDiffs(path) {
    //Calculate all degree-diffs to find the corners (=extremes)
    let diffsOrdered = [];
    let lastDegree = 0;
    for (let i = 0; i < path.length; i++) {
        let diff = PathHelper.getRotationGain(path, i, 10);

        let deg = PathHelper.getRotation(path, i, 10);
        while (lastDegree - deg > 180) deg += 360;
        while (lastDegree - deg < -180) deg -= 360;
        lastDegree = deg;

        diffsOrdered.push({offset: i, diff: diff, deg: deg, point: path.getPointAt(i)});
    }

    return diffsOrdered;
}

function getPieceCornerOffsets(diffs) {
    let peaks = PathHelper.getNegativePeaks(diffs);

    let points = [];
    for (let i = 0; i < diffs.length; i++) {
        points.push(diffs[i].point);
    }

    let distinctOffsets = [];
    for (let a = 3; a < Math.min(15, peaks.length); a++) {
        for (let b = 2; b < a; b++) {
            for (let c = 1; c < b; c++) {
                nextOffset: for (let d = 0; d < c; d++) {
                    let offsets = [peaks[a], peaks[b], peaks[c], peaks[d]];
                    offsets.sort((a, b) => a.offset - b.offset);

                    let degrees = [];
                    for (let i = 0; i < 4; i++) {
                        let degree = Math.atan2(offsets[(i+1)%4].point.y - offsets[i].point.y, offsets[(i+1)%4].point.x - offsets[i].point.x) * 180 / Math.PI;
                        while (degree > 180) {
                            degree -= 180;
                        }

                        degrees.push(degree);
                    }

                    //Check for degrees between the borders of 80 to 100 degree
                    for (let i = 0; i < 4; i++) {
                        let degreeDiff = degrees[i] - degrees[(i+1)%4];
                        while (degreeDiff < -180) {
                            degreeDiff += 180;
                        }
                        if (Math.abs(degreeDiff) < 75 || Math.abs(degreeDiff) > 105) {
                            continue nextOffset;
                        }
                    }

                    //Check for correct quadrats
                    let maxLength = null;
                    let minLength = null;
                    for (let i = 0; i < 4; i++) {
                        let length = MathHelper.distanceOfPoints(offsets[i].point, offsets[(i+1) % 4].point);

                        if (maxLength === null || length > maxLength) {
                            maxLength = length;
                        }

                        if (minLength === null || length < minLength) {
                            minLength = length;
                        }
                    }
                    if (minLength / maxLength < 0.5) {
                        continue;
                    }

                    //Check for straight sides for 10% before and after each corner
                    for (let i = 0; i < 4; i++) {
                        let offsetX = (offsets[(i+1) % 4].point.x - offsets[i].point.x) * 0.1;
                        let offsetY = (offsets[(i+1) % 4].point.y - offsets[i].point.y) * 0.1;
                        let comparePoint = {x: offsets[i].point.x + offsetX, y: offsets[i].point.y + offsetY};
                        if (MathHelper.distanceToPolyline(comparePoint, points) > Math.sqrt(offsetX * offsetX + offsetY * offsetY) * 0.2) {
                            continue nextOffset;
                        }
                    }

                    distinctOffsets.push([offsets[0], offsets[1], offsets[2], offsets[3]]);
                }
            }
        }
    }

    if (distinctOffsets.length === 0) {
        return null;
    }

    distinctOffsets.sort((a,b) => {
        let aDistance = MathHelper.distanceOfPoints(a[0].point, a[1].point) + MathHelper.distanceOfPoints(a[1].point, a[2].point) + MathHelper.distanceOfPoints(a[2].point, a[3].point) + MathHelper.distanceOfPoints(a[3].point, a[0].point);
        let bDistance = MathHelper.distanceOfPoints(b[0].point, b[1].point) + MathHelper.distanceOfPoints(b[1].point, b[2].point) + MathHelper.distanceOfPoints(b[2].point, b[3].point) + MathHelper.distanceOfPoints(b[3].point, b[0].point);

        return bDistance - aDistance;
    });

    return [distinctOffsets[0][0].offset, distinctOffsets[0][1].offset, distinctOffsets[0][2].offset, distinctOffsets[0][3].offset];
}

function getSide(path, fromOffset, toOffset) {
    let startPoint = path.getPointAt(fromOffset);
    let endPoint = path.getPointAt(toOffset);
    let directLength = Math.sqrt(Math.pow(endPoint.x - startPoint.x, 2) + Math.pow(endPoint.y - startPoint.y, 2));

    let middlePoint = {x: startPoint.x + (endPoint.x - startPoint.x) / 2, y: startPoint.y + (endPoint.y - startPoint.y) / 2};
    let rotation = Math.atan2(endPoint.y - startPoint.y, endPoint.x - startPoint.x);
    let rotationSin = Math.sin(rotation);
    let rotationCos = Math.cos(rotation);

    let points = [];
    for (let offset = fromOffset; offset <= toOffset + (toOffset < fromOffset ? path.length : 0); offset++) {
        let fixedOffset = Math.floor(offset % path.length);

        let point = {
            x: (path.getPointAt(fixedOffset).x - middlePoint.x) * rotationCos + (path.getPointAt(fixedOffset).y - middlePoint.y) * rotationSin,
            y: (path.getPointAt(fixedOffset).y - middlePoint.y) * rotationCos - (path.getPointAt(fixedOffset).x - middlePoint.x) * rotationSin
        };

        points.push(point);
    }

    points = PathHelper.simplifyPoints(points);/*
    points.unshift({x: -500, y: 0});
    points.push({x: 500, y: 0});*/

    if (!PathHelper.isStraightSide(points, directLength)) {
        return {
            points: points,
            direction: PathHelper.hasOutsideNop(points) ? 'out' : 'in',
            area: PathHelper.getArea(points),
            directLength: directLength,
            startPoint: startPoint,
            endPoint: endPoint,
            nop: PathHelper.getNopData(points)
        };
    }

    return null;
}

function analyzeBorders(paperPath) {
    return new Promise((fulfill, reject) => {
        let pieceIndex = nextPieceIndex++;

        //Detect corners
        Debug.startTime('getPieceDiffs');
        let diffs = getPieceDiffs(paperPath);
        Debug.endTime('getPieceDiffs');

        Debug.startTime('getPieceCornerOffsets');
        let cornerOffsets = getPieceCornerOffsets(diffs);
        Debug.endTime('getPieceCornerOffsets');

        if (cornerOffsets === null) {
            reject('No borders found.');
            return;
        }

        //Generate side arrays
        Debug.startTime('generateSideArrays');
        let sides = [];
        for (let i = 0; i < 4; i++) {
            let fromOffset = cornerOffsets[i];
            let toOffset = cornerOffsets[(i + 1) % 4];

            let side = getSide(paperPath, fromOffset, toOffset);
            if (side) {
                side.pieceIndex = pieceIndex;
                side.sideIndex = sides.length;
                side.fromOffset = fromOffset;
                side.toOffset = toOffset;
                sides.push(side);
            }
        }
        Debug.endTime('generateSideArrays');

        Cache.clear();

        fulfill({
            pieceIndex: pieceIndex,
            sides: sides,
            diffs: diffs
        });
    });
}

module.exports = {
    findMatchingPieces: findMatchingPieces,
    getSideMatchingFactor: getSideMatchingFactor,
    analyzeBorders: analyzeBorders
};