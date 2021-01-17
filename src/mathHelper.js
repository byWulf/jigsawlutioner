// noinspection JSUnusedLocalSymbols (needed for typehinting below)
const NodeCache = require('node-cache');
const DistanceStatistics = require('../model/DistanceStatistics');
const Paper = require('paper-jsdom');

//https://stackoverflow.com/questions/849211/shortest-distance-between-a-point-and-a-line-segment
function sqr(x) { return x * x }
function dist2(v, w) { return sqr(v.x - w.x) + sqr(v.y - w.y) }
function distToSegmentSquared(p, v, w) {
  let l2 = dist2(v, w);
  if (l2 === 0) return dist2(p, v);
  let t = ((p.x - v.x) * (w.x - v.x) + (p.y - v.y) * (w.y - v.y)) / l2;
  t = Math.max(0, Math.min(1, t));
  return dist2(p, { x: v.x + t * (w.x - v.x),
                    y: v.y + t * (w.y - v.y) });
}

/**
 * Gets the closest distance between a point and a (capped) line.
 *
 * @param {Point} point
 * @param {Point} lineStartPoint
 * @param {Point} lineEndPoint
 * @returns {number}
 */
function distanceToLine(point, lineStartPoint, lineEndPoint) {
    return Math.sqrt(distToSegmentSquared(point, lineStartPoint, lineEndPoint));
}

/**
 * Returns the shortest distance between the point and the line (consisting out of many points).
 *
 * @param {Point} point
 * @param {Point[]} polylinePoints
 * @returns {number|null}
 */
function distanceToPolyline(point, polylinePoints) {
    let closestDist = null;
    for (let j = 0; j < polylinePoints.length; j++) {
        let dist = distanceToLine(
            point,
            polylinePoints[j],
            typeof polylinePoints[j+1] !== 'undefined' ? polylinePoints[j+1] : polylinePoints[j]
        );

        if (closestDist === null || dist < closestDist) {
            closestDist = dist;
        }
    }

    return closestDist;
}

/**
 * Calculates the average distance and the maximum distance between two lines.
 *
 * @param {Point[]} sourcePoints
 * @param {Point[]} comparePoints
 * @param {number} offsetX
 * @param {number} offsetY
 * @param {NodeCache} cache
 * @returns {DistanceStatistics}
 */
function distancesOfPolylines(sourcePoints, comparePoints, offsetX, offsetY, cache) {
    const cacheKey = JSON.stringify([sourcePoints, comparePoints, offsetX, offsetY]);
    if (cache.has(cacheKey)) {
        return cache.get(cacheKey);
    }

    let distanceSum = 0;
    let maxDistance = 0;
    for (let i = 0; i < sourcePoints.length; i++) {
        let distance = distanceToPolyline(new Paper.Point(sourcePoints[i].x + offsetX, sourcePoints[i].y + offsetY), comparePoints);

        distanceSum += distance;

        if (distance > maxDistance) {
            maxDistance = distance;
        }
    }

    const result = new DistanceStatistics(
        distanceSum / sourcePoints.length,
        maxDistance
    );

    cache.set(cacheKey, result);

    return result;
}

/**
 * @param {Point} point1
 * @param {Point} point2
 * @returns {number}
 */
function distanceOfPoints(point1, point2) {
    let diffX = point2.x - point1.x;
    let diffY = point2.y - point1.y;
    return Math.sqrt(diffX * diffX + diffY * diffY);
}

/**
 *
 * @param {Point[]} points
 * @param {Point} point
 * @returns {Point}
 */
function getClosestPoint(points, point) {
    let closestPoint = null;
    for (let i = 0; i < points.length; i++) {
        let distance = distanceOfPoints(points[i], point);
        if (closestPoint === null || distance < closestPoint.distance) {
            closestPoint = {
                point: points[i],
                distance: distance
            };
        }
    }

    if (closestPoint === null) {
        throw new Error('Could not determine closest point, because we did not get a list of points.');
    }

    return closestPoint.point;
}

/**
 * Point1/2 will become 0° if rotated by the return value
 *
 * @param {Point} point1
 * @param {Point} point2
 * @param {Point} point3
 * @param {Point} point4
 *
 * @returns {number}
 */
function getRotationOfRectangle(point1, point2, point3, point4) {
    let points = [point1, point2, point3, point4];

    let rotations = [];
    for (let i = 0; i < 4; i++) {
        let singleRotation = -Math.atan2(
            points[(i + 1) % 4].y - points[i].y,
            points[(i + 1) % 4].x - points[i].x
        ) * 180 / Math.PI - (i + 1) * 90;

        rotations.push(singleRotation);
    }

    return getAverageRotation(rotations);
}

/**
 * @param {number[]} rotations
 * @returns {number}
 */
function getAverageRotation(rotations) {
    return 180 / Math.PI * Math.atan2(
        sum(rotations.map(degToRad).map(Math.sin)) / rotations.length,
        sum(rotations.map(degToRad).map(Math.cos)) / rotations.length
    );
}

/**
 * @param {number[]} a
 * @returns {number}
 */
function sum(a) {
    let s = 0;
    for (let i = 0; i < a.length; i++) {
        s += a[i];
    }
    return s;
}

/**
 * @param {number} degree
 * @returns {number}
 */
function degToRad(degree) {
    return Math.PI / 180 * degree;
}

module.exports = {
    distanceToLine: distanceToLine,
    distanceToPolyline: distanceToPolyline,
    distancesOfPolylines: distancesOfPolylines,
    distanceOfPoints: distanceOfPoints,
    getClosestPoint: getClosestPoint,
    getRotationOfRectangle: getRotationOfRectangle
};
