// $ node --max-old-space-size=8192 test/fittingPerformance

const BorderFinder = require(__dirname + '/../src/borderFinder');
const SideFinder = require(__dirname + '/../src/sideFinder');
const Matcher = require(__dirname + '/../src/matcher');

const dateFormat = require('dateformat');
const singleLog = require('single-line-log').stdout;
const roundTo = require('round-to');
const fs = require('fs');
const colors = require('colors/safe');

async function parseBorders(min, max, useCache) {
    let startTime = Date.now();
    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing borders');

    let cacheFile = __dirname + '/fixtures/fittingPerformance_borderPieces.json';
    if (useCache && fs.existsSync(cacheFile)) {
        let borderPieces = JSON.parse(fs.readFileSync(cacheFile));

        let count = 0;
        for (let i in borderPieces) {
            if (!borderPieces.hasOwnProperty(i)) continue;
            count++;
        }

        let avg = (Date.now() - startTime) / Math.max(count, 1);
        singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing borders (' + count + '/' + count + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Finished after ' + roundTo((Date.now() - startTime) / 1000, 1) + 's (from cache)');
    console.log('');

        return borderPieces;
    }

    let pieces = {};
    let done = 0;
    let sum = max - min + 1;
    let errors = [];
    for (let i = min; i <= max; i++) {
        try {
            pieces[i] = await BorderFinder.findPieceBorder(__dirname + '/fixtures/pieces/piece' + i + '.jpg', {
                threshold: 245,
                reduction: 2
            });
            done++;
        } catch (e) {
            errors.push(e);
        }

        let avg = (Date.now() - startTime) / Math.max(done, 1);

        singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing borders (' + done + '/' + sum + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Remaining ' + roundTo((avg * (sum - done)) / 1000, 1) + 's');
    }

    let avg = (Date.now() - startTime) / Math.max(done, 1);
    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing borders (' + done + '/' + sum + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Finished after ' + roundTo((Date.now() - startTime) / 1000, 1) + 's');
    console.log('');
    for (let i = 0; i < errors.length; i++) {
        console.log(errors[i]);
    }

    fs.writeFileSync(cacheFile, JSON.stringify(pieces));

    return pieces;
}

async function parseSides(pieces, useCache) {
    let startTime = Date.now();
    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing sides');

    let cacheFile = __dirname + '/fixtures/fittingPerformance_sidePieces.json';
    if (useCache && fs.existsSync(cacheFile)) {
        let sidePieces = JSON.parse(fs.readFileSync(cacheFile));

        let count = 0;
        for (let i in sidePieces) {
            if (!sidePieces.hasOwnProperty(i)) continue;
            count++;
        }

        let avg = (Date.now() - startTime) / Math.max(count, 1);
        singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing sides (' + count + '/' + count + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Finished after ' + roundTo((Date.now() - startTime) / 1000, 1) + 's (from cache)');
    console.log('');

        return sidePieces;
    }

    let sum = 0;
    for (let i in pieces) {
        if (!pieces.hasOwnProperty(i)) continue;
        sum++;
    }

    let newPieces = {};
    let done = 0;
    let errors = [];
    for (let i in pieces) {
        if (!pieces.hasOwnProperty(i)) continue;

        try {
            newPieces[i] = await SideFinder.findSides(i, pieces[i].path);
            done++;
        } catch (e) {
            errors.push(e);
        }

        let avg = (Date.now() - startTime) / Math.max(done, 1);

        singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing sides (' + done + '/' + sum + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Remaining ' + roundTo((avg * (sum - done)) / 1000, 1) + 's');
    }

    let avg = (Date.now() - startTime) / Math.max(done, 1);
    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Parsing sides (' + done + '/' + sum + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Finished after ' + roundTo((Date.now() - startTime) / 1000, 1) + 's');
    console.log('');
    for (let i = 0; i < errors.length; i++) {
        console.log(errors[i]);
    }

    fs.writeFileSync(cacheFile, JSON.stringify(newPieces));

    return newPieces;
}

async function generateFactorsMap(pieces, useCache) {
    let startTime = Date.now();
    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Generating factor map');

    let cacheFile = __dirname + '/fixtures/fittingPerformance_factorMap.json';
    if (useCache && fs.existsSync(cacheFile)) {
        let factorMap = JSON.parse(fs.readFileSync(cacheFile));

        let count = 0;
        for (let i in factorMap) {
            if (!factorMap.hasOwnProperty(i)) continue;
            count++;
        }

        let avg = (Date.now() - startTime) / Math.max(count, 1);
        singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Generating factor map (' + count + '/' + count + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Finished after ' + roundTo((Date.now() - startTime) / 1000, 1) + 's (from cache)');
    console.log('');

        return factorMap;
    }

    let piecesArray = [];
    for (let i in pieces) {
        if (!pieces.hasOwnProperty(i)) continue;

        piecesArray.push(pieces[i]);
    }

    let errors = [];
    let factorMap = null;
    try {
        factorMap = Matcher.generateFactorsMap(piecesArray, (done, sum) => {
            let avg = (Date.now() - startTime) / Math.max(done, 1);

            singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Generating factor map (' + done + '/' + sum + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Remaining ' + roundTo((avg * (sum - done)) / 1000, 1) + 's');
        });
    } catch (e) {
        errors.push(e);
    }

    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Generating factor map - Finished after ' + roundTo((Date.now() - startTime) / 1000, 1) + 's');
    console.log('');
    for (let i = 0; i < errors.length; i++) {
        console.log(errors[i]);
    }

    fs.writeFileSync(cacheFile, JSON.stringify(factorMap));

    return factorMap;
}

async function match(pieces, factorMap, options, clearOutput) {
    let startTime = Date.now();
    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Getting placements');

    let piecesArray = [];
    for (let i in pieces) {
        if (!pieces.hasOwnProperty(i)) continue;

        piecesArray.push(pieces[i]);
    }    

    let errors = [];
    let placements = null;
    try {
        placements = Matcher.getPlacements(piecesArray, factorMap, options, (done, sum) => {
            let avg = (Date.now() - startTime) / Math.max(done, 1);

            singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Getting placements (' + done + '/' + sum + ') - Avg ' + roundTo(avg / 1000, 3) + 's - Remaining ' + roundTo((avg * (sum - done)) / 1000, 1) + 's');
        });
    } catch (e) {
        errors.push(e);
    }

    singleLog('[' + dateFormat(startTime, 'yyyy-mm-dd HH:MM:ss') + '] Getting placements - Finished after ' + roundTo((Date.now() - startTime) / 1000, 1) + 's');
    if (clearOutput) {
        singleLog('');
        singleLog.clear();
    } else {
        console.log('');
    }
    for (let i = 0; i < errors.length; i++) {
        console.log(errors[i]);
    }

    return placements;
}

function getPerfectMatching(pieces) {
    let placements = [{}];

    for (let i in pieces) {
        if (!pieces.hasOwnProperty(i)) continue;

        let x = (i - 2) % 25;
        let y = Math.floor((i - 2) / 25);

        if (typeof placements[0][x] === 'undefined') placements[0][x] = {};
        placements[0][x][y] = pieces[i];
        placements[0][x][y].rotation = 1;
    }

    return placements;
}

function getFittingPerformance(placements) {
    let rotationMapping = {
        0: {index: -1, yLimit: 0, xOffset: 0, yOffset: -1},
        1: {index: +25, xLimit: 0, xOffset: -1, yOffset: 0},
        2: {index: +1, yLimit: 19, xOffset: 0, yOffset: 1},
        3: {index: -25, xLimit: 24, xOffset: 1, yOffset: 0}
    };

    let matchSum = 0;
    let correctSum = 0;
    for (let g = 0; g < placements.length; g++) {
        for (let x in placements[g]) {
            if (!placements[g].hasOwnProperty(x)) continue;

            for (let y in placements[g][x]) {
                if (!placements[g][x].hasOwnProperty(y)) continue;

                let index = parseInt(placements[g][x][y].pieceIndex, 10);
                let rotation = placements[g][x][y].rotation;

                let rightX = (index - 2) % 25;
                let rightY = Math.floor((index - 2) / 25);

                for (let r = 0; r < 4; r++) {
                    let matchingX = parseInt(x) + rotationMapping[(r + rotation + 0) % 4].xOffset;
                    let matchingY = parseInt(y) + rotationMapping[(r + rotation + 0) % 4].yOffset;

                    let matchingPiece = null;
                    if (
                        typeof placements[g][matchingX] !== 'undefined' &&
                        typeof placements[g][matchingX][matchingY] !== 'undefined'
                    ) {
                        matchingPiece = placements[g][matchingX][matchingY];
                    }

                    let correct = false;
                    if (
                        (typeof rotationMapping[r].xLimit !== 'undefined' && rightX === rotationMapping[r].xLimit) ||
                        (typeof rotationMapping[r].yLimit !== 'undefined' && rightY === rotationMapping[r].yLimit)
                    ) {
                        correct = matchingPiece === null;
                    } else {
                        correct = matchingPiece !== null && parseInt(matchingPiece.pieceIndex) === (index + rotationMapping[r].index) && matchingPiece.rotation === rotation;
                    }

                    matchSum++;
                    if (correct) {
                        correctSum++;
                    }
                }
            }
        }
    }
    
    return correctSum / Math.max(matchSum, 1);
}

function correctnessCheck(pieceIndex, sideIndex, comparePieceIndex, compareSideIndex) {
    let rotationMapping = {
        0: {index: -1, xLimit: 0, xOffset: -1, yOffset: 0},
        1: {index: +25, yLimit: 19, xOffset: 0, yOffset: 1},
        2: {index: +1, xLimit: 24, xOffset: 1, yOffset: 0},
        3: {index: -25, yLimit: 0, xOffset: 0, yOffset: -1}
    };

    let rightX = (pieceIndex - 2) % 25;
    let rightY = Math.floor((pieceIndex - 2) / 25);

    if (
        (typeof rotationMapping[sideIndex].xLimit !== 'undefined' && rightX === rotationMapping[sideIndex].xLimit) ||
        (typeof rotationMapping[sideIndex].yLimit !== 'undefined' && rightY === rotationMapping[sideIndex].yLimit)
    ) {
        return comparePieceIndex === null;
    }

    if (comparePieceIndex === null) return false;
    if (Math.abs(compareSideIndex - sideIndex) !== 2) return false;

    return comparePieceIndex === pieceIndex + rotationMapping[sideIndex].index;
}

function outputDeviationStats(factorMap) {
    let compares = [
        {xLimit: 23, yLimit: null, xOffset: 1, yOffset: 0, side: 2, indexAddition: 1},
        {xLimit: null, yLimit: 18, xOffset: 0, yOffset: 1, side: 1, indexAddition: 25}
    ];

    let count = 0;
    let sums = {
        avgDistance: 0,
        worstSingleDistance: 0,
        directLengthDiff: 0,
        areaDiff: 0,
        smallNopDiff: 0,
        bigNopDiff: 0,
        nopHeightDiff: 0,
        nopCenterDiff: 0
    };
    for (let x = 0; x < 25; x++) {
        for (let y = 0; y < 20; y++) {
            let pieceIndex = y * 25 + x + 2;

            for (let i = 0; i < compares.length; i++) {
                if (compares[i].xLimit !== null && x > compares[i].xLimit) continue;
                if (compares[i].yLimit !== null && y > compares[i].yLimit) continue;

                let match = factorMap[Matcher.getFactorMapKey(pieceIndex, compares[i].side, pieceIndex + compares[i].indexAddition, (compares[i].side + 2) % 4)];
                if (!match || !match.matches) continue;

                sums.avgDistance += match.avgDistance;
                sums.worstSingleDistance += match.worstSingleDistance;
                sums.directLengthDiff += match.directLengthDiff;
                sums.areaDiff += match.areaDiff;
                sums.smallNopDiff += match.smallNopDiff;
                sums.bigNopDiff += match.bigNopDiff;
                sums.nopHeightDiff += match.nopHeightDiff;
                sums.nopCenterDiff += match.nopCenterDiff;

                count++;
            }
        }
    }
    console.log('avgDistance', sums.avgDistance / count);
    console.log('worstSingleDistance', sums.worstSingleDistance / count);
    console.log('directLengthDiff', sums.directLengthDiff / count);
    console.log('areaDiff', sums.areaDiff / count);
    console.log('smallNopDiff', sums.smallNopDiff / count);
    console.log('bigNopDiff', sums.bigNopDiff / count);
    console.log('nopHeightDiff', sums.nopHeightDiff / count);
    console.log('nopCenterDiff', sums.nopCenterDiff / count);
}

(async () => {
    try {
        let borderPieces = await parseBorders(2, 501, true);

        let sidePieces = await parseSides(borderPieces, true);

        let factorMap = await generateFactorsMap(sidePieces, true);

        let placements = await match(sidePieces, factorMap, null, false);
        //let placements = getPerfectMatching(sidePieces);
        Matcher.outputPlacements(placements, correctnessCheck);
        let performance = getFittingPerformance(placements);
        console.log("Result: " + roundTo(performance * 100, 1) + "%");

        //TODO: Angeblich hat [4.725,0.462,2,0.76,1.134,0.945,1.26,1.575,0.085,-2,0,4503599627370495,-2,1,1.01,0.99,0.99,1,1,1] 87.5% .... aber die bigNopOffset ist irrwitzig groß....

/*        outputDeviationStats(factorMap);

        let baseOptionSet = [4.725,0.462,2,0.76,1.134,0.945,1.26,1.575,0.085,-2,0,-1,-2,1,1.01,0.99,1,1,1,1];
        let changeFactors = [-0.05, +0.05];

        while (true) {
            let optionSets = [baseOptionSet];
            for (let i = 0; i < baseOptionSet.length; i++) {
                for (let f = 0; f < changeFactors.length; f++) {
                    /!*for (let i2 = i + 1; i2 < baseOptionSet.length; i2++) {
                        for (let f2 = 0; f2 < changeFactors.length; f2++) {*!/
                            let optionSet = [];
                            for (let j = 0; j < baseOptionSet.length; j++) {
                                if (j >= 9 && j <= 12 && (i === j)) {
                                    optionSet.push(roundTo(baseOptionSet[j] + (10 * changeFactors[f]), 4));
                                } else if (j < 9 && (i === j)) {
                                    optionSet.push(roundTo(baseOptionSet[j] * (1 + changeFactors[f]), 4));
                                } else if (j > 12 && (i === j)) {
                                    optionSet.push(roundTo(baseOptionSet[j] * (1 + changeFactors[f] * 0.2), 4));
                                /!*} else if (j >= 9 && j <= 12 && (i2 === j)) {
                                    optionSet.push(roundTo(baseOptionSet[j] + (10 * changeFactors[f2]), 4));
                                } else if (j < 9 && (i2 === j)) {
                                    optionSet.push(roundTo(baseOptionSet[j] * (1 + changeFactors[f2]), 4));
                                } else if (j > 12 && (i2 === j)) {
                                    optionSet.push(roundTo(baseOptionSet[j] * (1 + changeFactors[f2] * 0.01), 4));*!/
                                } else {
                                    optionSet.push(roundTo(baseOptionSet[j], 4));
                                }
                            }
                            optionSets.push(optionSet);
                        /!*}
                    }*!/
                }
            }

            let bestResult = null;
            for (let i = 0; i < optionSets.length; i++) {

                let options = {
                    avgDistanceFactor: optionSets[i][0],//5.25,
                    directLengthDiffFactor: optionSets[i][1],//0.44000000000000006,
                    worstSingleDistanceFactor: optionSets[i][2],//2,
                    nopCenterDiffFactor: optionSets[i][3],//0.76,
                    nopHeightDiffFactor: optionSets[i][4],//1.05,
                    smallNopDiffFactor: optionSets[i][5],//0.9,
                    bigNopDiffFactor: optionSets[i][6],//1.26,

                    moreSidesBetterFactor: optionSets[i][7],//1.5,
                    noDistinctionLimit: optionSets[i][8],//0.085,

                    avgDistanceOffset: optionSets[i][9],//0,
                    directLengthDiffOffset: optionSets[i][10],//0,
                    bigNopDiffOffset: optionSets[i][11],//-0.5,
                    smallNopDiffOffset: optionSets[i][12],//0,
                    
                    avgDistancePow: optionSets[i][13],//1,
                    directLengthDiffPow: optionSets[i][14],//1.01,
                    worstSingleDistancePow: optionSets[i][15],//0.99,
                    nopCenterDiffPow: optionSets[i][16],//1,
                    nopHeightDiffPow: optionSets[i][17],//1,
                    smallNopDiffPow: optionSets[i][18],//1,
                    bigNopDiffPow: optionSets[i][19],//1
                };
                console.log(options);

                let placements = await match(sidePieces, factorMap, options, true);
                //Matcher.outputPlacements(placements, correctnessCheck);
                let performance = getFittingPerformance(placements);
                console.log(JSON.stringify(optionSets[i]) + ": " + roundTo(performance * 100, 1) + "%");

                if (bestResult === null || performance > bestResult.performance) {
                    bestResult = {
                        index: i,
                        performance: performance
                    };
                }
            }

            if (bestResult.index === 0) {
                console.log(colors.red("Nothing better found than " + roundTo(bestResult.performance * 100, 1) + "%. increasing the change factors"));

                for (let f = 0; f < changeFactors.length; f++) {
                    changeFactors[f] *= 2;
                }
            } else {
                console.log(colors.green("New best set: ", optionSets[bestResult.index], roundTo(bestResult.performance * 100, 1) + "%"));

                baseOptionSet = optionSets[bestResult.index];
                changeFactors = [-0.05, +0.05];
            }
        }*/
    } catch (e) {
        console.log(e);
    }
})();
