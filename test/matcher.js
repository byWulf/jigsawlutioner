const assert = require('assert');

const Matcher = require('../src/matcher');

describe('Matcher', function() {
    describe('#getFreePlaces()', function () {
        [
            [
                [{x: 0, y: 0}],
                [{x: -1, y: 0}, {x: 0, y: 1}, {x: 1, y: 0}, {x: 0, y: -1}]
            ], [
                [{x: 3, y: 1}, {x: 4, y: 1}],
                [{x: 2, y: 1}, {x: 3, y: 2}, {x: 4, y: 2}, {x: 5, y: 1}, {x: 4, y: 0}, {x: 3, y: 0}]
            ], [
                [{x: -2, y: 6}, {x: -1, y: 6}, {x: 0, y: 6}, {x: -2, y: 7}, {x: 0, y: 7}, {x: -2, y: 8}, {x: -1, y: 8}, {x: 0, y: 8}],
                [{x: -2, y: 5}, {x: -1, y: 5}, {x: 0, y: 5}, {x: -3, y: 6}, {x: 1, y: 6}, {x: -3, y: 7}, {x: -1, y: 7}, {x: 1, y: 7}, {x: -3, y: 8}, {x: 1, y: 8}, {x: -2, y: 9}, {x: -1, y: 9}, {x: 0, y: 9}]
            ]
        ].forEach((dataSet) => {
            it('should return ' + JSON.stringify(dataSet[1]) + ' when group is ' + JSON.stringify(dataSet[0]), () => {
                let group = {};
                for (let i = 0; i < dataSet[0].length; i++) {
                    if (typeof group[dataSet[0][i].x] === 'undefined') group[dataSet[0][i].x] = {};
                    group[dataSet[0][i].x][dataSet[0][i].y] = 1;
                }

                let places = Matcher.getFreePlaces(group);
                assert.equal(places.length, dataSet[1].length, 'Correct amount of free places');

                for (let i = 0; i < dataSet[1].length; i++) {
                    for (let j = 0; j < places.length; j++) {
                        if (places[j].x === dataSet[1][i].x && places[j].y === dataSet[1][i].y) {
                            places.splice(j, 1);
                            break;
                        }
                    }
                }
                assert.equal(places.length, 0, 'All required free places were given');
            });
        });
    });
});