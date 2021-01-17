
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
 * @param point
 * @param lineStartPoint
 * @param lineEndPoint
 * @returns {number}
 */
function distanceToLine(point, lineStartPoint, lineEndPoint) {
    return Math.sqrt(distToSegmentSquared(point, lineStartPoint, lineEndPoint));
}

/**
 * Returns the shortest distance between the point and the line (consisting out of many points).
 *
 * @param point
 * @param polylinePoints
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
 * @param sourcePoints
 * @param comparePoints
 * @param offsetX
 * @param offsetY
 * @param {Cache} cache
 * @returns {*}
 */
function distancesOfPolylines(sourcePoints, comparePoints, offsetX, offsetY, cache) {
    let cacheKey = JSON.stringify([sourcePoints, comparePoints, offsetX, offsetY]);
    if (cache.has(cacheKey)) {
        return cache.get(cacheKey);
    }

    let distanceSum = 0;
    let maxDistance = null;
    for (let i = 0; i < sourcePoints.length; i++) {
        let distance = distanceToPolyline({x: sourcePoints[i].x + offsetX, y: sourcePoints[i].y + offsetY}, comparePoints);

        distanceSum += distance;

        if (maxDistance === null || distance > maxDistance) {
            maxDistance = distance;
        }
    }

    let result = {
        avgDistance: distanceSum / sourcePoints.length,
        maxDistance: maxDistance
    };

    cache.set(cacheKey, result);

    return result;
}

function distanceOfPoints(point1, point2) {
    point1 = fixPoint(point1);
    point2 = fixPoint(point2);

    let diffX = point2.x - point1.x;
    let diffY = point2.y - point1.y;
    return Math.sqrt(diffX * diffX + diffY * diffY);
}

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

    if (closestPoint) {
        return closestPoint.point;
    }
    return null;
}

/**
 * Point1/2 will become 0° if rotated by the return value
 *
 * @param point1
 * @param point2
 * @param point3
 * @param point4
 */
function getRotationOfRectangle(point1, point2, point3, point4) {
    let points = [point1, point2, point3, point4];

    let keyY = 'y';
    let keyX = 'x';
    if (points[0] instanceof Array) {
        keyY = 2;
        keyX = 1;
    }

    let rotations = [];
    for (let i = 0; i < 4; i++) {
        let singleRotation = -Math.atan2(
            points[(i + 1) % 4][keyY] - points[i][keyY],
            points[(i + 1) % 4][keyX] - points[i][keyX]
        ) * 180 / Math.PI - (i + 1) * 90;

        rotations.push(singleRotation);
    }

    return getAverageRotation(rotations);
}

function getAverageRotation(rotations) {
    return 180 / Math.PI * Math.atan2(
        sum(rotations.map(degToRad).map(Math.sin)) / rotations.length,
        sum(rotations.map(degToRad).map(Math.cos)) / rotations.length
    );
}
function sum(a) {
    var s = 0;
    for (let i = 0; i < a.length; i++) {
        s += a[i];
    }
    return s;
}

function degToRad(a) {
    return Math.PI / 180 * a;
}

function fixPoint(numericalPoint) {
    if (typeof numericalPoint.x !== 'undefined') return numericalPoint;

    return {x: numericalPoint[1], y: numericalPoint[2]};
}

module.exports = {
    distanceToLine: distanceToLine,
    distanceToPolyline: distanceToPolyline,
    distancesOfPolylines: distancesOfPolylines,
    distanceOfPoints: distanceOfPoints,
    getClosestPoint: getClosestPoint,
    getRotationOfRectangle: getRotationOfRectangle
};
