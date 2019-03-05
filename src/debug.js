const colors = require('colors/safe');
const mathHelper = require('./mathHelper');

class Debug {
    outputPlacements(placements, correctnessChecker) {
        if (typeof correctnessChecker !== 'function') {
            correctnessChecker = () => null;
        }
        const colorize = (string, group, x, y, realSide) => {
            const sideOpposites = {
                0: {x: 0, y: -1},
                1: {x: -1, y: 0},
                2: {x: 0, y: 1},
                3: {x: 1, y: 0}
            };

            let piece = placements[group][x][y];
            let comparePiece = typeof placements[group][x + sideOpposites[realSide].x] !== 'undefined' ? placements[group][x + sideOpposites[realSide].x][y + sideOpposites[realSide].y] : null;

            let result = correctnessChecker(
                parseInt(piece.pieceIndex),
                (realSide - piece.rotation + 4) % 4,
                comparePiece ? parseInt(comparePiece.pieceIndex) : null,
                comparePiece ? (realSide - comparePiece.rotation + 4 + 2) % 4 : null
            );

            if (result === true) return colors.green(string);
            if (result === false) return colors.red(string);
            return string;
        };

        for (let g = 0; g < placements.length; g++) {
            let groupPlacements = placements[g];

            let minX = 0, maxX = 0, minY = 0, maxY = 0;
            for (let x in groupPlacements) {
                if (!groupPlacements.hasOwnProperty(x)) continue;

                minX = Math.min(minX, x);
                maxX = Math.max(maxX, x);

                for (let y in groupPlacements[x]) {
                    if (!groupPlacements[x].hasOwnProperty(y)) continue;

                    minY = Math.min(minY, y);
                    maxY = Math.max(maxY, y);
                }
            }
            console.log("Gruppe " + g + ":");

            let str1 = '';
            for (let y = minY; y <= maxY; y++) {
                str1 = '';
                let str2 = '';
                let str3 = '';
                let str4 = '';
                let str5 = '';
                for (let x = minX; x <= maxX; x++) {
                    let pieceIndex = 0;
                    let rotation = 0;
                    let customX = 0;
                    let customY = 0;
                    let customPieceIndex = 0;
                    if (typeof groupPlacements[x] !== 'undefined' && typeof groupPlacements[x][y] !== 'undefined') {
                        pieceIndex = groupPlacements[x][y].pieceIndex;
                        rotation = groupPlacements[x][y].rotation;
                        customY = Math.floor((pieceIndex - 2) / 25) + 1;
                        customX = (pieceIndex - 2) - ((customY - 1) * 25) + 1;

                        customY = (Math.abs(customY) < 10 ? ' ' : '') + (customY >= 0 ? ' ' : '') + customY;
                        customX = (Math.abs(customX) < 10 ? ' ' : '') + (customX >= 0 ? ' ' : '') + customX;
                        customPieceIndex = (pieceIndex < 100 ? ' ' : '') + (pieceIndex < 10 ? ' ' : '') + pieceIndex;
                    }

                    if (pieceIndex > 0) {
                        str1 += '+      ';
                        str2 += ' ' + (rotation === 0 ? '●' : '┌');
                        str3 += ' ';
                        str4 += ' ';
                        str5 += ' ' + (rotation === 1 ? '●' : '└');

                        if (groupPlacements[x][y].sides[(0 - rotation + 4) % 4]) {
                            let direction = groupPlacements[x][y].sides[(0 - rotation + 4) % 4].direction;//┌┐└┘─│
                            if (direction === 'straight') str2 += colorize('────',g, x, y, 0);
                            if (direction === 'in') str2 += colorize('─┐┌─',g, x, y, 0);
                            if (direction === 'out') str2 += colorize('─┘└─',g, x, y, 0);
                        } else {
                            str2 += '    ';
                        }

                        if (groupPlacements[x][y].sides[(1 - rotation + 4) % 4]) {
                            let direction = groupPlacements[x][y].sides[(1 - rotation + 4) % 4].direction;
                            if (direction === 'straight') {
                                str3 += colorize('│',g, x, y, 1);
                                str4 += colorize('│',g, x, y, 1);
                            }
                            if (direction === 'in') {
                                str3 += colorize('└',g, x, y, 1);
                                str4 += colorize('┌',g, x, y, 1);
                            }
                            if (direction === 'out') {
                                str3 += colorize('┘',g, x, y, 1);
                                str4 += colorize('┐',g, x, y, 1);
                            }
                        } else {
                            str3 += ' ';
                            str4 += ' ';
                        }

                        str3 += customX + ' ';
                        str4 += customY + ' ';

                        if (groupPlacements[x][y].sides[(3 - rotation + 4) % 4]) {
                            let direction = groupPlacements[x][y].sides[(3 - rotation + 4) % 4].direction;
                            if (direction === 'straight') {
                                str3 += colorize('│',g, x, y, 3);
                                str4 += colorize('│',g, x, y, 3);
                            }
                            if (direction === 'in') {
                                str3 += colorize('┘',g, x, y, 3);
                                str4 += colorize('┐',g, x, y, 3);
                            }
                            if (direction === 'out') {
                                str3 += colorize('└',g, x, y, 3);
                                str4 += colorize('┌',g, x, y, 3);
                            }
                        } else {
                            str3 += ' ';
                            str4 += ' ';
                        }

                        if (groupPlacements[x][y].sides[(2 - rotation + 4) % 4]) {
                            let direction = groupPlacements[x][y].sides[(2 - rotation + 4) % 4].direction;
                            if (direction === 'straight') str5 += colorize('────',g, x, y, 2);
                            if (direction === 'in') str5 += colorize('─┘└─',g, x, y, 2);
                            if (direction === 'out') str5 += colorize('─┐┌─',g, x, y, 2);
                        } else {
                            str5 += '    ';
                        }

                        str2 += rotation === 3 ? '●' : '┐';
                        str5 += rotation === 2 ? '●' : '┘';
                    } else {
                        str1 += '+      ';
                        str2 += '       ';
                        str3 += '       ';
                        str4 += '       ';
                        str5 += '       ';
                    }

                }

                str1 += '+';
                str2 += ' ';
                str3 += ' ';
                str4 += ' ';
                str5 += ' ';

                console.log(str1);
                console.log(str2);
                console.log(str3);
                console.log(str4);
                console.log(str5);
            }
            console.log(str1);
            console.log("\n");
        }
    }

    async createPlacementsImage(placements, options) {
        if (typeof options !== 'object') options = {};
        let pieceSize = options['pieceSize'] || 48;

        let width = 0;
        let height = 0;
        nextGroup: for (let g = 0; g < placements.length; g++) {
            let groupPlacements = placements[g];

            for (let x in groupPlacements) {
                if (!groupPlacements.hasOwnProperty(x)) continue;

                for (let y in groupPlacements[x]) {
                    if (!groupPlacements[x].hasOwnProperty(y)) continue;

                    let piece = groupPlacements[x][y];
                    if (typeof piece.sides === 'undefined' || !(piece.sides instanceof Array) || piece.sides.length !== 4) continue;

                    width = Math.max(width, piece.groupSizes.maxX - piece.groupSizes.minX + 1);
                    height += piece.groupSizes.maxY - piece.groupSizes.minY + 1 + 2;

                    continue nextGroup;
                }
            }
        }

        const Canvas = require('canvas');
        const sharp = require('sharp');

        const canvas = new Canvas((width + 1) * pieceSize, (height + 1) * pieceSize);
        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, (width + 1) * pieceSize, (height + 1) * pieceSize);

        let currentY = 0;
        for (let g = 0; g < placements.length; g++) {
            let groupPlacements = placements[g];

            context.font = Math.floor(pieceSize * 0.25) + 'px Tahoma';
            context.fillStyle = '#000000';
            context.fillText('Group #' + g, pieceSize * 0.1, (currentY + 0.5) * pieceSize);

            let groupSizes = {
                minx: 0,
                maxX: 0,
                minY: 0,
                maxY: 0
            };

            for (let x in groupPlacements) {
                if (!groupPlacements.hasOwnProperty(x)) continue;

                for (let y in groupPlacements[x]) {
                    if (!groupPlacements[x].hasOwnProperty(y)) continue;

                    let piece = groupPlacements[x][y];
                    if (typeof piece.sides === 'undefined' || !(piece.sides instanceof Array) || piece.sides.length !== 4) continue;
                    if (typeof piece.images === 'undefined' || typeof piece.images.transparent === 'undefined') continue;

                    let img = new Canvas.Image();
                    img.src = Buffer.from(piece.images.transparent.buffer, piece.images.transparent.encoding);

                    let resizeFactor = pieceSize / piece.groupSizes.avgWidth;

                    let xKey = typeof piece.sides[0].startPoint.x !== 'undefined' ? 'x' : 1;
                    let yKey = typeof piece.sides[0].startPoint.y !== 'undefined' ? 'y' : 2;

                    let centerX = (piece.boundingBox.left + (piece.sides[0].startPoint[xKey] + piece.sides[1].startPoint[xKey] + piece.sides[2].startPoint[xKey] + piece.sides[3].startPoint[xKey]) / 4) * piece.images.transparent.resizeFactor;
                    let centerY = (piece.boundingBox.top + (piece.sides[0].startPoint[yKey] + piece.sides[1].startPoint[yKey] + piece.sides[2].startPoint[yKey] + piece.sides[3].startPoint[yKey]) / 4) * piece.images.transparent.resizeFactor;

                    let pieceCanvasSize = Math.sqrt(piece.dimensions.width * piece.dimensions.width + piece.dimensions.height * piece.dimensions.height) * piece.images.transparent.resizeFactor;
                    let pieceCanvas = new Canvas(pieceCanvasSize, pieceCanvasSize);
                    let pieceContext = pieceCanvas.getContext('2d');

                    pieceContext.drawImage(img, pieceCanvasSize / 2 - centerX, pieceCanvasSize / 2 - centerY);

                    let rotationCanvas = new Canvas(pieceCanvasSize, pieceCanvasSize);
                    let rotationContext = rotationCanvas.getContext('2d');

                    rotationContext.translate(pieceCanvasSize / 2, pieceCanvasSize / 2);
                    rotationContext.rotate(piece.correctPosition.rotation * Math.PI/180);

                    rotationContext.drawImage(pieceCanvas, -pieceCanvasSize / 2, -pieceCanvasSize / 2);

                    let resizedCanvasSize = pieceCanvasSize * resizeFactor / piece.images.transparent.resizeFactor;

                    context.drawImage(
                        rotationCanvas,
                        0,
                        0,
                        pieceCanvasSize,
                        pieceCanvasSize,
                        piece.correctPosition.x * resizeFactor - resizedCanvasSize / 2,
                        (currentY + 1) * pieceSize + piece.correctPosition.y * resizeFactor - resizedCanvasSize / 2,
                        resizedCanvasSize,
                        resizedCanvasSize);

                    groupSizes = piece.groupSizes;
                }
            }

            currentY += groupSizes.maxY - groupSizes.minY + 2;
        }

        return canvas.toBuffer('image/png').toString('base64');
    }
}

module.exports = new Debug();