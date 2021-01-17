const Paper = require('paper-jsdom');
const MathHelper = require('./mathHelper');

const NopData = require('../model/NopData');
const Distance = require('../model/Distance');

/**
 * Gets the tangents rotation (in degree) from a specific offset in a (closed) paperjs path.
 * Specify a high threshold to get a better result on shaky paths.
 *
 * @param {Path} path
 * @param {number} offset
 * @param {number} threshold
 * @returns {number}
 */
function getRotation(path, offset, threshold = 4) {
    let point1 = path.getPointAt((offset + path.length - threshold) % path.length);
    let point2 = path.getPointAt((offset + threshold) % path.length);

    return Math.atan2(point2.y - point1.y, point2.x - point1.x) * 180 / Math.PI;
}

/**
 * Gets the difference in
 * @param {Path} path
 * @param {number} offset
 * @param {number} threshold
 * @returns {number}
 */
function getRotationGain(path, offset, threshold) {
    if (typeof threshold === 'undefined') threshold = 4;

    let previousRotation = getRotation(path, offset - threshold, threshold);
    let nextRotation = getRotation(path, offset + threshold, threshold);
    let diff = nextRotation - previousRotation;
    if (diff > 180) diff -= 360;
    if (diff <= -180) diff += 360;

    return diff;
}

/**
 * Returns true, if the given side is straight without a nop.
 *
 * @param {Point[]} points
 * @param {number} sideLength
 * @returns {boolean}
 */
function isStraightSide(points, sideLength) {
    for (let j = 0; j < points.length; j++) {
        if (Math.abs(points[j].y) > sideLength * 0.1) {
            return false;
        }
    }

    return true;
}

/**
 * Returns true, if the side has its nop to the outside. Outside is defined in the path as "down"/"below the x-axis"
 *
 * @param {Point[]} points
 * @returns {boolean}
 */
function hasOutsideNop(points) {
    let max = 0;
    for (let i = 0; i < points.length; i++) {
        if (Math.abs(points[i].y) > Math.abs(max)) {
            max = points[i].y;
        }
    }

    return max < 0;
}

/**
 * Rotates the given points by 180 degree.
 *
 * @param {Point[]} points
 * @returns {Point[]}
 */
function rotatePoints(points) {
    let newPoints = [];

    for (let i = 0; i < points.length; i++) {
        newPoints.push({x: -points[i].x, y: -points[i].y});
    }

    return newPoints;
}

/**
 * Returns the negative peak diffs from a set of diffs
 * @param {Diff[]} diffs
 * @returns {Diff[]}
 */
function getNegativePeaks(diffs) {
    diffs.sort((a,b) => a.offset - b.offset);

    let peaks = [];
    let peakStart = null;
    if (diffs[diffs.length - 1].diff >= diffs[0].diff) {
        peakStart = 0;
    }
    for (let i = 0; i < diffs.length; i++) {
        if (diffs[(i + 1) % diffs.length].diff < diffs[i].diff) {
            peakStart = i + 1;
        }
        if (peakStart !== null && diffs[(i + 1) % diffs.length].diff > diffs[i].diff) {
            peaks.push(diffs[Math.round(peakStart + ((i - peakStart) / 2))]);
            peakStart = null;
        }
    }

    peaks.sort((a,b) => a.diff - b.diff);

    return peaks;
}

/**
 *
 * @param {Point[]} points
 * @returns {number}
 */
function getArea(points) {
    Paper.setup(new Paper.Size(1,1));
    let paperPath = new Paper.Path({
        closed: true,
        segments: points
    });

    return paperPath.area;
}

/**
 * @param {Point[]} points
 * @returns {NopData}
 */
function getNopData(points) {
    let extremePointIndex = 0;
    for (let i = 0; i < points.length; i++) {
        if (Math.abs(points[i].y) > Math.abs(points[extremePointIndex].y)) {
            extremePointIndex = i;
        }
    }

    let leftExtremeIndex = extremePointIndex;
    for (let i = leftExtremeIndex - 1; i >= 0; i--) {
        if (Math.abs(points[i].y) >= Math.abs(points[extremePointIndex].y) / 2 && points[i].x <= points[leftExtremeIndex].x) {
            if (points[i].x <= points[leftExtremeIndex].x) {
                leftExtremeIndex = i;
            }
        }
    }
    let rightExtremeIndex = extremePointIndex;
    for (let i = rightExtremeIndex + 1; i < points.length; i++) {
        if (Math.abs(points[i].y) >= Math.abs(points[extremePointIndex].y) / 2 && points[i].x >= points[rightExtremeIndex].x) {
            rightExtremeIndex = i;
        }
    }

    let leftMinimumIndex = leftExtremeIndex;
    for (let i = leftMinimumIndex - 1; i >= 0; i--) {
        if (points[i].x >= points[leftMinimumIndex].x) {
            leftMinimumIndex = i;
        }
    }
    let rightMinimumIndex = rightExtremeIndex;
    for (let i = rightMinimumIndex + 1; i < points.length; i++) {
        if (points[i].x <= points[rightMinimumIndex].x) {
            rightMinimumIndex = i;
        }
    }

    return new NopData(
        new Distance(points[leftExtremeIndex].x, points[rightExtremeIndex].x),
        new Distance(points[leftMinimumIndex].x, points[rightMinimumIndex].x),
        points[extremePointIndex].y
    );
}

/**
 * @param {Point[]} points
 * @returns {Point[]}
 */
function simplifyPoints(points) {
    let finishedPoints = points.slice(0);

    outerLoop: for (let p1 = 0; p1 < finishedPoints.length; p1++) {
        for (let p2 = p1 + 2; p2 < finishedPoints.length; p2++) {
            for (let p = p1 + 1; p < p2; p++) {
                if (MathHelper.distanceToLine(finishedPoints[p], finishedPoints[p1], finishedPoints[p2]) > 1.5) {
                    finishedPoints.splice(p1 + 1, p2 - p1 - 2);
                    continue outerLoop;
                }
            }
        }
        finishedPoints.splice(p1 + 1, finishedPoints.length - p1 - 2);
        break;
    }

    return finishedPoints;
}

module.exports = {
    getRotation: getRotation,
    getRotationGain: getRotationGain,
    isStraightSide: isStraightSide,
    hasOutsideNop: hasOutsideNop,
    getArea: getArea,
    rotatePoints: rotatePoints,
    getNegativePeaks: getNegativePeaks,
    getNopData: getNopData,
    simplifyPoints: simplifyPoints
};
