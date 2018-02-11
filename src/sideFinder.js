const MathHelper = require('./mathHelper');
const PathHelper = require('./pathHelper');
const Cache = require('./cache');

function getPieceDiffs(path) {
    //Calculate all degree-diffs to find the corners (=extremes)
    let diffsOrdered = [];
    let lastDegree = 0;
    let diffCount = 2000;
    let factor = path.length / diffCount;
    for (let i = 0; i < diffCount; i++) {
        let pathOffset = i * factor;

        let diff = PathHelper.getRotationGain(path, pathOffset, 10);

        let deg = PathHelper.getRotation(path, pathOffset, 10);
        while (lastDegree - deg > 180) deg += 360;
        while (lastDegree - deg < -180) deg -= 360;
        lastDegree = deg;

        diffsOrdered.push({offset: pathOffset, diff: diff, deg: deg, point: path.getPointAt(pathOffset)});
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
                        /*for (let dist = 0.03; dist <= 0.15; dist += 0.03) {
                            let offsetX = (offsets[(i + 1) % 4].point.x - offsets[i].point.x) * dist;
                            let offsetY = (offsets[(i + 1) % 4].point.y - offsets[i].point.y) * dist;
                            let comparePoint = {x: offsets[i].point.x + offsetX, y: offsets[i].point.y + offsetY};
                            if (MathHelper.distanceToPolyline(comparePoint, points) > Math.sqrt(offsetX * offsetX + offsetY * offsetY) * 0.4) {
                                //if (a == 3 && b == 2 && c == 1 && d == 0) console.log(" => anfang/ende passt nicht");
                                continue nextOffset;
                            }
                        }*/

                        let index = null;
                        for (let j = 0; j <= diffs.length; j++) {
                            if (diffs[j].offset == offsets[i].offset) {
                                index = j;
                                break;
                            }
                        }
                        if (index === null) {
                            continue nextOffset;
                        }

                        let started = false;
                        let goodLength = 0;
                        for (let j = index; j <= index + 150; j++) {
                            if (!started && Math.abs(diffs[j%2000].diff) < 10) {
                                started = true;
                            }
                            if (started && Math.abs(diffs[j%2000].diff) < 15) {
                                goodLength++;
                            }
                            if (started && Math.abs(diffs[j%2000].diff) >= 15) {
                                break;
                            }
                        }
                        if (goodLength < 30) {
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

function getSide(path, fromOffset, toOffset, colorPoints) {
    let startPoint = path.getPointAt(fromOffset);
    let endPoint = path.getPointAt(toOffset);
    let directLength = Math.sqrt(Math.pow(endPoint.x - startPoint.x, 2) + Math.pow(endPoint.y - startPoint.y, 2));

    let middlePoint = {x: startPoint.x + (endPoint.x - startPoint.x) / 2, y: startPoint.y + (endPoint.y - startPoint.y) / 2};
    let rotation = Math.atan2(endPoint.y - startPoint.y, endPoint.x - startPoint.x);
    let rotationSin = Math.sin(rotation);
    let rotationCos = Math.cos(rotation);

    let points = [];
    let length = toOffset + (toOffset < fromOffset ? path.length : 0) - fromOffset;
    let steps = 50;
    for (let i = 0; i < steps; i++) {
        let fixedOffset = Math.floor((fromOffset + i * (length / (steps - 1))) % path.length);
        let pointAt = path.getPointAt(fixedOffset);
        let colorPoint = colorPoints ? MathHelper.getClosestPoint(colorPoints, pointAt) : null;

        let point = {
            x: (pointAt.x - middlePoint.x) * rotationCos + (pointAt.y - middlePoint.y) * rotationSin,
            y: (pointAt.y - middlePoint.y) * rotationCos - (pointAt.x - middlePoint.x) * rotationSin,
            color: colorPoint ? colorPoint.color : null
        };

        points.push(point);
    }

    let isStraight = PathHelper.isStraightSide(points, directLength);

    return {
        points: points,
        direction: isStraight ? 'straight' : PathHelper.hasOutsideNop(points) ? 'out' : 'in',
        area: PathHelper.getArea(points),
        directLength: directLength,
        startPoint: startPoint,
        endPoint: endPoint,
        nop: PathHelper.getNopData(points)
    };
}

function findSides(pieceIndex, paperPath, colorPoints, options) {
    if (typeof options !== 'object') {
        options = {}
    }
    if (typeof options.debug === 'undefined') {
        options.debug = false;
    }
    if (typeof options.filename === 'undefined') {
        options.filename = '';
    }

    return new Promise((fulfill, reject) => {
        //Detect corners
        let diffs = getPieceDiffs(paperPath);

        let cornerOffsets = getPieceCornerOffsets(diffs);

        //Generate side arrays
        let sides = [];
        if (cornerOffsets !== null) {
            for (let i = 0; i < 4; i++) {
                let fromOffset = cornerOffsets[i];
                let toOffset = cornerOffsets[(i + 1) % 4];

                let side = getSide(paperPath, fromOffset, toOffset, colorPoints);
                side.pieceIndex = pieceIndex;
                side.sideIndex = sides.length;
                side.fromOffset = fromOffset;
                side.toOffset = toOffset;
                sides.push(side);
            }


            if (options.debug && typeof options.filename === 'string' && options.filename) {
                saveSideImage(options.filename, sides);
            }
        }

        Cache.clear();

        fulfill({
            pieceIndex: pieceIndex,
            sides: sides,
            diffs: diffs
        });
    });
}

function saveSideImage(filename, sides) {
    const Canvas = require('canvas');
    const fs = require('fs');

    const canvas = new Canvas(500,1200);
    const context = canvas.getContext('2d');

    for (let sideIndex = 0; sideIndex < sides.length; sideIndex++) {
        let side = sides[sideIndex];
        for (let i = 0; i < side.points.length; i++) {
            let radius = 5;
            context.beginPath();
            context.arc(side.points[i].x + 200, side.points[i].y + 200 + sideIndex * 250, radius, 0, 2 * Math.PI, false);
            context.fillStyle = 'rgb(' + side.points[i].color[0] + ', ' + side.points[i].color[1] + ', ' + side.points[i].color[2] + ')';
            context.fill();
        }
    }

    let out = fs.createWriteStream(filename + '.sides.png');
    let stream = canvas.pngStream();

    stream.on('data', function(chunk){
      out.write(chunk);
    });
}

module.exports = {
    findSides: findSides,
    getPieceDiffs: getPieceDiffs,
    getPieceCornerOffsets: getPieceCornerOffsets
};