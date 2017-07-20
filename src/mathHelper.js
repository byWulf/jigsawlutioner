const Cache = require('./cache');

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
 * @returns {*}
 */
function distancesOfPolylines(sourcePoints, comparePoints, offsetX, offsetY) {
    if (Cache.has([sourcePoints, comparePoints, offsetX, offsetY])) {
        return Cache.get([sourcePoints, comparePoints, offsetX, offsetY]);
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
    
    Cache.set([sourcePoints, comparePoints, offsetX, offsetY], result);
    
    return result;
}

function distanceOfPoints(point1, point2) {
    let diffX = point2.x - point1.x;
    let diffY = point2.y - point1.y;
    return Math.sqrt(diffX * diffX + diffY * diffY);
}

module.exports = {
    distanceToLine: distanceToLine,
    distanceToPolyline: distanceToPolyline,
    distancesOfPolylines: distancesOfPolylines,
    distanceOfPoints: distanceOfPoints
};