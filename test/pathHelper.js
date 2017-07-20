const assert = require('assert');

const paper = require('paper-jsdom');
const PathHelper = require('../src/pathHelper');

describe('PathHelper', function() {
    describe('#getRotation()', function () {
        paper.setup(new paper.Size(100, 100));
        let path = new paper.Path();
        for (let point of [
            {x: -1, y: 0},
            {x: -1 + 0.3826834, y: 1 - 0.9238795},
            {x: -1 + 0.7071068, y: 1 - 0.7071068},
            {x: -1 + 0.9238795, y: 1 - 0.3826834},
            {x: 0, y: 1},
            {x: 1 - 0.9238795, y: 1 + 0.3826834},
            {x: 1 - 0.7071068, y: 1 + 0.7071068},
            {x: 1 - 0.3826834, y: 1 + 0.9238795},
            {x: 1, y: 2}
        ]) {
            path.add(new paper.Point(point.x, point.y));
        }


        [
            [0, 0],
            [60.165249059042964, 1],
            [-122.74004514810944, 2],
            [-101.25000560955415, 3],
        ].forEach((dataSet) => {
            //TODO: Threshold should be relative to ... yea to what? directLength of the side?
            it('should return ' + dataSet[0] + ' degree with threshold ' + dataSet[1] + ' and the given points', function() {
                assert.equal(PathHelper.getRotation(path, path.length / 2, dataSet[1]), dataSet[0]);
            });
        });
    });
    describe('#getRotationGain()', function () {

    });
    describe('#getRotationGainAverage()', function () {

    });
    describe('#simplifyPoints()', function () {

    });
    describe('#isStraightSide()', function () {
        [
            [true, [{x: -100, y: 0}, {x: -50, y: 0}, {x: 0, y: 0}, {x: 50, y: 0}, {x: 100, y: 0}], 200],
            [true, [{x: -100, y: 15}, {x: -50, y: -19}, {x: 0, y: -4}, {x: 50, y: 18}, {x: 100, y: 0}], 200],
            [false, [{x: -100, y: 15}, {x: -50, y: -19}, {x: 0, y: -4}, {x: 50, y: 18}, {x: 100, y: 0}], 100],
            [false, [{x: -100, y: 15}, {x: -50, y: -21}, {x: 0, y: -4}, {x: 50, y: 18}, {x: 100, y: 0}], 200],
            [true, [{x: -100, y: 15}, {x: -50, y: -21}, {x: 0, y: -4}, {x: 50, y: 18}, {x: 100, y: 0}], 300],
        ].forEach((dataSet) => {
            it('should return ' + (dataSet[0] ? 'true' : 'false') + ' for the points ' + dataSet[1].map((value) => value.x + '/' + value.y).join(', ') + ' and a side length of ' + dataSet[2], function() {
                assert.equal(PathHelper.isStraightSide(dataSet[1], dataSet[2]), dataSet[0]);
            });
        });
    });
    describe('#hasOutsideNop()', function () {
        [
            [true, [{x: -10, y: 0}, {x: 0, y: -10}, {x: 10, y: 0}]],
            [false, [{x: -10, y: 0}, {x: 0, y: 10}, {x: 10, y: 0}]],
            [true, [{x: -10, y: 0}, {x: -1, y: 10}, {x: 0, y: -11}, {x: 1, y: 10}, {x: 10, y: 0}]],
        ].forEach((dataSet) => {
            it('should return ' + (dataSet[0] ? 'true' : false) + ' for the points ' + dataSet[1].map((value) => value.x + '/' + value.y).join(', '), function() {
                assert.equal(PathHelper.hasOutsideNop(dataSet[1]), dataSet[0]);
            });
        });
    });
    describe('#rotatePoints()', function () {
        [
            [[{x: 0, y: -0}], [{x: -0, y: 0}]],
            [[{x: 1, y: -1}], [{x: -1, y: 1}]],
            [[{x: -1, y: 1}], [{x: 1, y: -1}]],
            [[{x: 1, y: -1}, {x: -5, y: -3}, {x: 8, y: 4}, {x: -1, y: -1}, {x: 0, y: 0}], [{x: -1, y: 1}, {x: 5, y: 3}, {x: -8, y: -4}, {x: 1, y: 1}, {x: 0, y: 0}]],
        ].forEach((dataSet) => {
            it('should return the correct rotated points for the points ' + dataSet[1].map((value) => value.x + '/' + value.y).join(', '), function() {
                assert.deepEqual(PathHelper.rotatePoints(dataSet[1]), dataSet[0]);
            });
        });
    });
    describe('#getNegativePeaks()', function () {
        [
            [[{offset: 6, diff: -10}, {offset: 4, diff: -8}, {offset: 10, diff: -7}],[{offset: 0, diff: -6}, {offset: 1, diff: -5}, {offset: 2, diff: -4}, {offset: 3, diff: -4}, {offset: 4, diff: -8}, {offset: 5, diff: -4}, {offset: 6, diff: -10}, {offset: 7, diff: -7}, {offset: 8, diff: -5}, {offset: 9, diff: 2}, {offset: 10, diff: -7}]],
            [[{offset: 4, diff: -10}, {offset: 6, diff: -8}, {offset: 0, diff: -7}],[{offset: 0, diff: -7}, {offset: 1, diff: 2}, {offset: 2, diff: -5}, {offset: 3, diff: -7}, {offset: 4, diff: -10}, {offset: 5, diff: -4}, {offset: 6, diff: -8}, {offset: 7, diff: -4}, {offset: 8, diff: -4}, {offset: 9, diff: -5}, {offset: 10, diff: -6}]],
            [[{offset: 1, diff: -1}],[{offset: 0, diff: -1}, {offset: 1, diff: -1}, {offset: 2, diff: -1}, {offset: 3, diff: 5}, {offset: 4, diff: -1}]],
        ].forEach((dataSet) => {
            it('should return ' + dataSet[0].map((value) => value.offset + '/' + value.diff).join(', ') + ' for the diffs ' + dataSet[1].map((value) => value.offset + '/' + value.diff).join(', '), function() {
                assert.deepEqual(PathHelper.getNegativePeaks(dataSet[1]), dataSet[0]);
            });
        });
    });
});