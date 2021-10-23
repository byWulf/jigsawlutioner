class DistanceStatistics {
    /**
     * @type {number}
     */
    avgDistance;

    /**
     * @type {number}
     */
    maxDistance;

    /**
     * @param {number} avgDistance
     * @param {number} maxDistance
     */
    constructor(avgDistance, maxDistance) {
        this.avgDistance = avgDistance;
        this.maxDistance = maxDistance;
    }
}

module.exports = DistanceStatistics;
