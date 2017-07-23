const cv = require('opencv');
const paper = require('paper-jsdom');

const MathHelper = require('./mathHelper');
const PathHelper = require('./pathHelper');
const OpencvHelper = require('./opencvHelper');
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
 * @returns {*}
 */
function getSideMatchingFactor(sourceSide, targetSide) {
    //Caching to reduce calculation time
    if (Cache.has(['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, targetSide.pieceIndex, targetSide.sideIndex])) {
        return Cache.get(['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, targetSide.pieceIndex, targetSide.sideIndex]);
    }

    Debug.countIteration('getSideMatchFactor');

    //Check form of the sides
    let bestResult = null;
    for (let offsetY = -3; offsetY <= 3; offsetY++) {
        for (let offsetX = -3; offsetX <= 3; offsetX++) {
            Debug.countIteration('getSideMatchingFactor_distances');

            Debug.startTime('getSideMatchingFactor_distancesOfPolylines');
            let distances = MathHelper.distancesOfPolylines(PathHelper.rotatePoints(sourceSide.points), targetSide.points, offsetX, offsetY);
            Debug.endTime('getSideMatchingFactor_distancesOfPolylines');

            if (bestResult === null || distances.avgDistance < bestResult.avgDistance) {
                bestResult = {
                    avgDistance: distances.avgDistance,
                    worstSingleDistance: distances.maxDistance,
                    offsetX: offsetX,
                    offsetY: offsetY
                };
            }
        }
    }

    Cache.set(['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, targetSide.pieceIndex, targetSide.sideIndex], bestResult);

    return bestResult;
}

/**
 * Returns the pieces, who could match on any side with the given piece.
 *
 * @param piece
 * @param pieces
 * @returns {Array.<*>}
 */
function findMatchingPieces(piece, pieces) {
    let matchingPieces = [];
    for (let comparePiece of pieces) {
        if (comparePiece.pieceIndex === piece.pieceIndex) continue;

        for (let sideIndex = 0; sideIndex < piece.sides.length; sideIndex++) {
            for (let compareSideIndex = 0; compareSideIndex < comparePiece.sides.length; compareSideIndex++) {
                if (
                    piece.sides[sideIndex].direction === comparePiece.sides[compareSideIndex].direction ||
                    Math.abs(comparePiece.sides[compareSideIndex].directLength - piece.sides[sideIndex].directLength) > 4
                ) {
                    continue;
                }

                let match = getSideMatchingFactor(piece.sides[sideIndex], comparePiece.sides[compareSideIndex]);

                if (match.avgDistance <= 4 &&
                    match.worstSingleDistance <= 7
                ) {
                    matchingPieces.push(comparePiece);
                }
            }
        }
    }

    matchingPieces.sort((a,b) => a.avgDistance - b.avgDistance);

    Debug.saveCompareSides(piece, pieces, piece.filename);

    Cache.clear();

    return matchingPieces.filter((item, pos) => matchingPieces.indexOf(item) === pos);
}

function getPieceDiffs(path) {
    //Calculate all degree-diffs to find the corners (=extremes)
    let diffsOrdered = [];
    let lastDegree = 0;
    for (let i = 0; i < path.length; i++) {
        let diff = PathHelper.getRotationGain(path, i);

        let deg = PathHelper.getRotation(path, i);
        while (lastDegree - deg > 180) deg += 360;
        while (lastDegree - deg < -180) deg -= 360;
        lastDegree = deg;

        diffsOrdered.push({offset: i, diff: diff, deg: deg, point: path.getPointAt(i)});
    }

    return diffsOrdered;
}

function getPieceCornerOffsets(diffs) {
    let peaks = PathHelper.getNegativePeaks(diffs);

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
                        if (Math.abs(degreeDiff) < 80 || Math.abs(degreeDiff) > 100) {
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

    points = PathHelper.simplifyPoints(points);
    points.unshift({x: -500, y: 0});
    points.push({x: 500, y: 0});

    if (!PathHelper.isStraightSide(points, directLength)) {
        return {
            points: points,
            direction: PathHelper.hasOutsideNop(points) ? 'out' : 'in',
            directLength: directLength,
            startPoint: startPoint,
            endPoint: endPoint
        };
    }

    return null;
}

function analyzeFile(filename) {
    return new Promise((fulfill, reject) => {
        //Load image
        cv.readImage(filename, (err, img) => {
            let pieceIndex = nextPieceIndex++;

            //Detect piece contour
            Debug.startTime('findContours');
            let contour = OpencvHelper.findContours(img, 200, 300);
            Debug.endTime('findContours');

            Debug.startTime('debug');
            contour.drawOnImage(img, [0, 255, 0], 2, 8, 0);
            Debug.saveMask(contour, filename);
            Debug.endTime('debug');

            //Detect corners
            Debug.startTime('getPieceDiffs');
            let diffs = getPieceDiffs(contour.path);
            Debug.endTime('getPieceDiffs');

            Debug.startTime('getPieceCornerOffsets');
            let cornerOffsets = getPieceCornerOffsets(diffs);
            Debug.endTime('getPieceCornerOffsets');

            if (cornerOffsets === null) {
                reject('No borders found.');
                return;
            }

            Debug.startTime('debug');
            Debug.saveSingleGraph(diffs, cornerOffsets, filename);
            Debug.endTime('debug');

            //Generate side arrays
            Debug.startTime('generateSideArrays');
            let sides = [];
            for (let i = 0; i < 4; i++) {
                let fromOffset = cornerOffsets[i];
                let toOffset = cornerOffsets[(i + 1) % 4];

                let side = getSide(contour.path, fromOffset, toOffset);
                if (side) {
                    side.pieceIndex = pieceIndex;
                    side.sideIndex = sides.length;
                    sides.push(side);

                    OpencvHelper.drawOutlinedText(img, side.startPoint.x + ((side.endPoint.x - side.startPoint.x) / 2), side.startPoint.y + ((side.endPoint.y - side.startPoint.y) / 2), (sides.length - 1));
                    OpencvHelper.drawOutlinedCross(img, side.startPoint.x, side.startPoint.y);
                }
            }
            Debug.endTime('generateSideArrays');

            Debug.startTime('debug');
            img.save(filename.replace('.jpg', '_finished.jpg'));
            Debug.endTime('debug');
            Cache.clear();

            fulfill({pieceIndex: pieceIndex, sides: sides, filename: filename.replace('.jpg', '_finished.jpg')});
        });
    });
}

module.exports = {
    findMatchingPieces: findMatchingPieces,
    getSideMatchingFactor: getSideMatchingFactor,
    analyzeFile: analyzeFile
};