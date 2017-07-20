const assert = require('assert');

const MathHelper = require('../src/mathHelper');

describe('MathHelper', function() {
    describe('#distanceToLine()', function() {
        [
            [0, {x: 0, y: 0}, {x: 0, y: 0}, {x: 0, y: 0}],
            [1, {x: 1, y: 0}, {x: 0, y: 0}, {x: 0, y: 0}],
            [1, {x: -1, y: 0}, {x: 0, y: 0}, {x: 0, y: 0}],
            [1, {x: 0, y: 1}, {x: 0, y: 0}, {x: 0, y: 0}],
            [1, {x: 0, y: -1}, {x: 0, y: 0}, {x: 0, y: 0}],
            [0, {x: 2, y: 2}, {x: 2, y: 2}, {x: 3, y: 4}],
            [0, {x: 2.5, y: 3}, {x: 2, y: 2}, {x: 3, y: 4}],
            [0, {x: 3, y: 4}, {x: 2, y: 2}, {x: 3, y: 4}],
            [Math.sqrt(2*2 + 1*1), {x: 0, y: 3}, {x: 2, y: 2}, {x: 3, y: 4}],
            [Math.sqrt(2*2 + 1*1), {x: 1, y: 5}, {x: 2, y: 2}, {x: 3, y: 4}],
            [1, {x: 3, y: 5}, {x: 2, y: 2}, {x: 3, y: 4}],
            [1, {x: 4, y: 4}, {x: 2, y: 2}, {x: 3, y: 4}],
            [Math.sqrt(1*1 + 1*1), {x: 4, y: 5}, {x: 2, y: 2}, {x: 3, y: 4}],
            [1, {x: 1, y: 2}, {x: 2, y: 2}, {x: 3, y: 4}],
            [1, {x: 2, y: 1}, {x: 2, y: 2}, {x: 3, y: 4}],
            [Math.sqrt(1*1 + 1*1), {x: 1, y: 1}, {x: 2, y: 2}, {x: 3, y: 4}],
        ].forEach((dataSet) => {
            it('should return ' + dataSet[0] + ' when startPoint is at ' + dataSet[1].x + '/' + dataSet[1].y + ' and the line is from ' + dataSet[2].x + '/' + dataSet[2].y + ' to ' + dataSet[3].x + '/' + dataSet[3].y, () => {
                assert.equal(MathHelper.distanceToLine(dataSet[1], dataSet[2], dataSet[3]), dataSet[0]);
            });
        });
    });

    describe('#distanceToPolyline()', function() {
        let points = [
            {x: 3, y: -4},
            {x: 6, y: -4},
            {x: 9, y: -2},
            {x: 9, y: 3},
            {x: 7, y: 6},
            {x: 4, y: 7},
            {x: 3, y: 10}
        ];

        [
            [0, {x: 3, y: -4}],
            [0, {x: 9, y: 0}],
            [0, {x: 5.5, y: 6.5}],
            [3, {x: 12, y: 0}],
            [Math.sqrt(4*4 + 1*1), {x: 13, y: 4}],
            [Math.sqrt(2*2 + 3*3), {x: 9.5, y: -6}],
            [Math.sqrt(1*1 + 1.5*1.5), {x: 6.5, y: -1.5}],
        ].forEach((dataSet) => {
            it('sould return ' + dataSet[0] + ' when searchPoint is at ' + dataSet[1].x + '/' + dataSet[1].y + ' and line points are ' + points.map((value) => value.x + '/' + value.y).join(', '), function() {
                assert.equal(MathHelper.distanceToPolyline(dataSet[1], points), dataSet[0]);
            });
        });
    });

    describe('#distancesOfPolylines()', function() {
        [
            [0, 0,  [{x: 0, y: 0}], [{x: 0, y: 0}], {x: 0, y: 0}],
            [1, 1,  [{x: 0, y: 0}], [{x: 1, y: 0}], {x: 0, y: 0}],
            [1, 1,  [{x: 0, y: 0}, {x: 0, y: 5}], [{x: -1, y: -1}, {x: -1, y: 2}, {x: -1, y: 6}], {x: 0, y: 0}],
            [(Math.sqrt(2)*2+1)/3, Math.sqrt(2),  [{x: -1, y: -1}, {x: -1, y: 2}, {x: -1, y: 6}], [{x: 0, y: 0}, {x: 0, y: 5}], {x: 0, y: 0}],
            [2/3, 2,  [{x: -1, y: -1}, {x: -1, y: 2}, {x: -1, y: 6}], [{x: 0, y: 0}, {x: 0, y: 5}], {x: 1, y: -1}],
            [0, 0, [{x: 3, y: -4}, {x: 6, y: -4}, {x: 9, y: -2}, {x: 9, y: 3}, {x: 7, y: 6}, {x: 4, y: 7}, {x: 3, y: 10}], [{x: 3, y: -4}, {x: 6, y: -4}, {x: 9, y: -2}, {x: 9, y: 3}, {x: 7, y: 6}, {x: 4, y: 7}, {x: 3, y: 10}], {x: 0, y: 0}],
            [1.329, 2, [{x: 3, y: -6}, {x: 6, y: -6}, {x: 9, y: -4}, {x: 9, y: 1}, {x: 7, y: 4}, {x: 4, y: 5}, {x: 3, y: 8}], [{x: 3, y: -4}, {x: 6, y: -4}, {x: 9, y: -2}, {x: 9, y: 3}, {x: 7, y: 6}, {x: 4, y: 7}, {x: 3, y: 10}], {x: 0, y: 0}],
            [1.329, 2, [{x: 3, y: -4}, {x: 6, y: -4}, {x: 9, y: -2}, {x: 9, y: 3}, {x: 7, y: 6}, {x: 4, y: 7}, {x: 3, y: 10}], [{x: 3, y: -4}, {x: 6, y: -4}, {x: 9, y: -2}, {x: 9, y: 3}, {x: 7, y: 6}, {x: 4, y: 7}, {x: 3, y: 10}], {x: 0, y: -2}],
        ].forEach((dataSet) => {
            it('should return an average of ' + dataSet[0] + ' and a maximum of ' + dataSet[1] + ' when comparing line ' + dataSet[2].map((value) => value.x + '/' + value.y).join(', ') + ' with line ' + dataSet[3].map((value) => value.x + '/' + value.y).join(', ') + ' and with an offset of ' + dataSet[4].x + '/' + dataSet[4].y, function() {
                let result = MathHelper.distancesOfPolylines(dataSet[2], dataSet[3], dataSet[4].x, dataSet[4].y);
                assert.equal(Math.round(result.avgDistance * 1000) / 1000, Math.round(dataSet[0] * 1000) / 1000);
                assert.equal(Math.round(result.maxDistance * 1000) / 1000, Math.round(dataSet[1] * 1000) / 1000);
            });
        });
    });

    describe('#distanceOfPoints()', function() {
        [
            [0, {x: 0, y: 0}, {x: 0, y: 0}],
            [0, {x: 2, y: -1}, {x: 2, y: -1}],
            [5, {x: 1, y: -1}, {x: 1, y: 4}],
            [4, {x: 1, y: -2}, {x: 5, y: -2}],
            [Math.sqrt(2), {x: 1, y: 0}, {x: 2, y: -1}],
        ].forEach((dataSet) => {
            it('should return ' + dataSet[0] + ' for points ' + dataSet[1].x + '/' + dataSet[1].y + ' and ' + dataSet[2].x + '/' + dataSet[2].y, function() {
                assert.equal(MathHelper.distanceOfPoints(dataSet[1], dataSet[2]), dataSet[0]);
            });
        })
    });
});