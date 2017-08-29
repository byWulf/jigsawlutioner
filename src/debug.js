const fs = require('fs');

const Cache = require('./cache');

const tableDelimiter = '\t';

/**
 * Most outputed txt files can be pasted into https://plot.ly/create/. See function description for usage.
 */

/**
 * Good to see how the system compared the side shapes.
 *
 * Columns A and B are the shape of the sourcePiece. Each side of this piece is displayed side by side on the x-axis (from left to right the correct indices of the sides; compare to the _finished.jpg helper image).
 * After that (beginning with column C and D) the sides for each compared piece are displayed vertically (from top to bottom the correct indices of the sides; compare to the _finished.jpg helper image).
 *
 * So f.e. if you want to see, how the given piece was matches to piece #4, then you have to create a "Line plot" with X-Column = A and Y-Column = B, and another "Line plot" with X-Column = I and Y-Column = J.
 *
 * @param sourcePiece
 * @param pieces
 * @param filename
 */
function saveCompareSides(sourcePiece, pieces, filename) {
    let graphString = '';
    let statisticsString = 'FromSide -> ToSide\tdirectLengthDiff\tavgDistance\tworstSingleDistance\toffsetX\toffsetY\n';
    for (let compareSideIndex = 0; compareSideIndex < 4; compareSideIndex++) {
        let y = -200 * compareSideIndex;

        //Move all lines to the front in the middle of the current and the previous row
        if (compareSideIndex > 0) {
            graphString += -400 + tableDelimiter + (y + 100) + tableDelimiter;
            for (let j = 0; j < pieces.length; j++) {
                graphString += -400 + tableDelimiter + (y + 100) + tableDelimiter;
            }
            graphString += '\n';
        }

        //Output every side of the source piece side by side in the current row
        for (let sourceSideIndex = 0; sourceSideIndex < sourcePiece.sides.length; sourceSideIndex++) {
            let sourceSide = sourcePiece.sides[sourceSideIndex];
            let x = sourceSideIndex * 800;

            for (let i = 0; i < sourceSide.points.length; i++) {
                graphString += (sourceSide.points[i].x + x) + tableDelimiter + (sourceSide.points[i].y + y) + tableDelimiter;

                //Add the current compare side to this point
                for (let j = 0; j < pieces.length; j++) {
                    let comparePiece = pieces[j];

                    let compareSide = comparePiece.sides[compareSideIndex];
                    if (sourcePiece.pieceIndex !== comparePiece.pieceIndex && compareSide) {
                        let centerIndexCorrection = Math.round((compareSide.points.length - sourceSide.points.length) / 2);

                        //TODO: Don't like it this way.. can't use Jigsawlutioner because of circular referencing...
                        //let match = Jigsawlutioner.getSideMatchingFactor(sourceSide, compareSide);
                        let match = Cache.get(['sideMatches', sourceSide.pieceIndex, sourceSide.sideIndex, compareSide.pieceIndex, compareSide.sideIndex]);

                        graphString += (match && compareSide.points[i + centerIndexCorrection] ? -compareSide.points[i + centerIndexCorrection].x + match.offsetX + x : x) + tableDelimiter +
                            (match && compareSide.points[i + centerIndexCorrection] ? -compareSide.points[i + centerIndexCorrection].y + match.offsetY + y : y) + tableDelimiter;

                        if (i === 0 && match) {
                            statisticsString +=
                                sourcePiece.pieceIndex + '/' + sourceSideIndex + '(' + (sourceSide.direction ? 'in ' : 'out') + ')' + ' -> ' + comparePiece.pieceIndex + '/' + compareSideIndex + '(' + (compareSide.direction ? 'in ' : 'out') + ')' + '\t' +
                                Math.abs(Math.round(match.directLengthDiff)) + '\t' +
                                Math.round(match.avgDistance) + '\t' +
                                Math.round(match.worstSingleDistance) + '\t' +
                                Math.round(match.offsetX) + '\t' +
                                Math.round(match.offsetY) + '\t' + '\n';
                        }
                    } else {
                        graphString += x + tableDelimiter + y + tableDelimiter;
                    }
                }

                graphString += '\n';
            }
        }

        //Move all lines at the end of the row to the middle between the current and the next row
        if (compareSideIndex < 3) {
            graphString += 2800 + tableDelimiter + (y - 100) + tableDelimiter;
            for (let j = 0; j < pieces.length; j++) {
                graphString += 2800 + tableDelimiter + (y - 100) + tableDelimiter;
            }
            graphString += '\n';
        }
    }

    fs.writeFile(filename.replace('.jpg', '_compareSidesGraph.txt'), graphString);
    fs.writeFile(filename.replace('.jpg', '_compareSidesStatistics.txt'), statisticsString);
}

/**
 * Good for analysing, how the sides of the piece where splitted.
 * X-Column A / Y-Column B = rotation gain
 * X-Column A / Y-Column C = rotation
 * X-Column A / Y-Column D = found corners
 *
 * @param diffsOrdered
 * @param distinctOffsets
 * @param filename
 */
function saveSingleGraph(diffsOrdered, distinctOffsets, filename) {
    let str = '';

    for (let i = 0; i < diffsOrdered.length; i++) {
        str += i + tableDelimiter + diffsOrdered[i].diff + tableDelimiter + diffsOrdered[i].deg + tableDelimiter + (distinctOffsets.indexOf(i) > -1 ? -200 : 200) + '\n';
    }

    fs.writeFile(filename.replace('.jpg', '_singlegraph.txt'), str);
}


let times = {};
let runningTimes = {};
let counts = {};

function getTime() {
    let time = process.hrtime();
    return time[0] * 1000000000 + time[1];
}

function startTime(name) {
    runningTimes[name] = getTime();
}

function endTime(name) {
    if (typeof times[name] === 'undefined') {
        times[name] = 0;
    }

    times[name] += getTime() - runningTimes[name];

    delete runningTimes[name];
}

function countIteration(name) {
    if (typeof counts[name] === 'undefined') {
        counts[name] = 0;
    }

    counts[name]++;
}

function output() {
    for (let name in times) {
        if (!times.hasOwnProperty(name)) continue;

        times[name] /= 1000000;
    }

    console.log({
        times: times,
        counts: counts
    });

    times = {};
    runningTimes = {};
    counts = {};
}


module.exports = {
    saveCompareSides: saveCompareSides,
    saveSingleGraph: saveSingleGraph,
    startTime: startTime,
    endTime: endTime,
    countIteration: countIteration,
    output: output
};