const path = require('path');
const sharp = require('sharp');
const Paper = require('paper');

const PathHelper = require('./pathHelper');
const MathHelper = require('./mathHelper');

function getColorPixel(data, x, y) {
    if (x < 0 || x >= data.info.width || y < 0 || y >= data.info.height) {
        return null;
    }

    return [
        data.data[(x + y * data.info.width) * data.info.channels + 0],
        data.data[(x + y * data.info.width) * data.info.channels + 1],
        data.data[(x + y * data.info.width) * data.info.channels + 2],
    ];
}
function getPixel(data, x, y) {
    if (x < 0 || x >= data.info.width || y < 0 || y >= data.info.height) {
        return null;
    }

    return data.data[x + y * data.info.width];
}
function setPixel(data, x, y, color) {
    if (x < 0 || x >= data.info.width || y < 0 || y >= data.info.height) {
        return;
    }

    data.data[x + y * data.info.width] = color;
}

function scanFill(data, startX, startY, fillWithColor) {
    let sourceColor = getPixel(data, startX, startY);

    let filledPixels = 0;
    let stack = [{x: startX, y: startY}];
    while (stack.length > 0) {
        let popElem = stack[stack.length - 1],
            x = popElem.x,
            y = popElem.y;

        setPixel(data, x, y, fillWithColor);
        filledPixels++;
        stack.length--;

        let saveX, xLeft, xRight, px;

        saveX = x;
        for( x--, px = getPixel(data, x, y); px !== null && (px === sourceColor || px === fillWithColor); x--, px = getPixel(data, x, y) ) {
            setPixel(data, x, y, fillWithColor);
            filledPixels++;
        }
        // save the extreme left px
        xLeft = x + 1;

        // fill the span to the right of the seed px
        x = saveX;
        for( x++, px = getPixel(data,  x, y ); px !== null && (px === sourceColor || px === fillWithColor); x++, px = getPixel(data,  x, y ) ) {
            setPixel(data, x, y, fillWithColor );
            filledPixels++;
        }
        // save the extreme right px
        xRight = x - 1;

        let currentY = y;

        [-1, 1].forEach((offset) => {
            let y = currentY + offset;

            let x = xLeft, px, pFlag;

            while( x <= xRight ) {
                // seed the scan line above
                pFlag = false;
                for( px = getPixel(data,  x, y ); px !== null && px === sourceColor && x <= xRight; x++, px = getPixel(data,  x, y ) ) {
                    pFlag = true;
                }
                if( pFlag ) {
                    if( x === xRight && px !== null && px === sourceColor ) {
                        stack.push( {x: x, y: y} );
                    } else {
                        stack.push( {x: x - 1, y: y} );
                    }
                }
                // continue checking in case the span is interrupted
                let xEnter = x;
                // noinspection StatementWithEmptyBodyJS
                for( px = getPixel(data,  x, y ); px !== null && px !== sourceColor && x <= xRight; x++, px = getPixel(data,  x, y ) );
                // make sure that the px coordinate is incremented
                if( x === xEnter ) {
                    x++;
                }
            }
        });
    }

    return filledPixels;
}

function replaceColor(data, sourceColor, newColor) {
    for (let x = 0; x < data.info.width; x++) {
        for (let y = 0; y < data.info.height; y++) {
            if (getPixel(data, x, y) === sourceColor) {
                setPixel(data, x, y, newColor);
            }
        }
    }
}

function getSizes(data, areaColor, replaceWithColor) {
    let sizes = [];

    for (let x = 0; x < data.info.width; x++) {
        for (let y = 0; y < data.info.height; y++) {
            if (getPixel(data, x, y) === areaColor) {
                let size = scanFill(data, x, y, replaceWithColor);
                sizes.push({x: x, y: y, size: size});
            }
        }
    }

    return sizes;
}

function removeSmallAreas(data, areaColor, biggestAreaColor, clearColor, minSize = 1000) {
    let sizes = getSizes(data, areaColor, biggestAreaColor);
    if (sizes.length === 0) {
        throw new Error('No areas found');
    }
    sizes.sort((a, b) => {
        return b.size - a.size;
    });
    for (let i = 0, length = sizes.length; i < length; i++) {
        if (sizes[i].size < minSize || sizes[i].size < sizes[0].size) {
            scanFill(data, sizes[i].x, sizes[i].y, clearColor + 1);
        }
    }
    replaceColor(data, clearColor + 1, clearColor);
}

function getOrderedBorderPoints(data, areaColor, simplifyPoints = true) {
    let points = [];

    let offsets = [{x: -1, y: 0}, {x: 0, y: 1}, {x: 1, y: 0}, {x: 0, y: -1}];
    for (let x = 0; x < data.info.width; x++) {
        for (let y = 0; y < data.info.height; y++) {
            if (getPixel(data, x, y) === areaColor) {
                //We know the first border gets to the bottom because it is the first pixel in the row...
                let direction = 1;
                let firstMovement = null;
                let boundingBox = {left: x, right: x, top: y, bottom: y};

                points.push({x: x, y: y});

                while (points.length < 3 || x !== firstMovement.x || y !== firstMovement.y || direction !== firstMovement.direction) {
                    //Start looking one direction back (if you went downwards.. first look leftwards)
                    for (let i = direction + 3; i < direction + 7; i++) {
                        let checkDirection = i % 4;
                        let pixel = getPixel(data, x + offsets[checkDirection].x, y + offsets[checkDirection].y);
                        if (pixel !== null && pixel === areaColor) {
                            x += offsets[checkDirection].x;
                            y += offsets[checkDirection].y;
                            direction = checkDirection;

                            points.push({x: x, y: y});
                            if (x < boundingBox.left) boundingBox.left = x;
                            if (x > boundingBox.right) boundingBox.right = x;
                            if (y < boundingBox.top) boundingBox.top = y;
                            if (y > boundingBox.bottom) boundingBox.bottom = y;

                            break;
                        }
                    }

                    if (firstMovement === null) {
                        firstMovement = {
                            x: x,
                            y: y,
                            direction: direction
                        };
                    }
                }

                points.pop();

                for (let i = 0, length = points.length; i < length; i++) {
                    points[i].x -= boundingBox.left;
                    points[i].y -= boundingBox.top;
                }

                return {
                    points: simplifyPoints ? PathHelper.simplifyPoints(points) : points,
                    boundingBox: boundingBox
                };
            }
        }
    }

    throw new Error('No areas found');
}


function getBorderPoints(data, areaColor) {
    let points = [];

    let surroundingOffsets = [[-1, 0], [1, 0], [0, -1], [0, 1]];
    for (let x = 0; x < data.info.width; x++) {
        for (let y = 0; y < data.info.height; y++) {
            if (getPixel(data, x, y) === areaColor) {
                let isBorderPixel = false;
                for (let i = 0, length = surroundingOffsets.length; i < length; i++) {
                    let pixel = getPixel(data, x + surroundingOffsets[i][0], y + surroundingOffsets[i][1]);
                    if (pixel !== null && pixel !== areaColor) {
                        isBorderPixel = true;
                        break;
                    }
                }
                if (isBorderPixel) {
                    points.push({x: x, y: y});
                }
            }
        }
    }

    return points;
}

function extendArea(data, areaColor, extendSize) {
    let borders = getBorderPoints(data, areaColor);

    for (let i = 0, length = borders.length; i < length; i++) {
        for (let x = -Math.ceil(extendSize / 2); x <= Math.ceil(extendSize / 2); x++) {
            for (let y = -Math.ceil(extendSize / 2); y <= Math.ceil(extendSize / 2); y++) {
                if (Math.round(Math.sqrt(x * x + y * y)) <= extendSize) {
                    setPixel(data, borders[i].x + x, borders[i].y + y, areaColor);
                }
            }
        }
    }
}

function replaceThinPixels(data, checkColor, minSurroundingPixels, surroundingColor) {
    for (let x = 0; x < data.info.width; x++) {
        for (let y = 0; y < data.info.height; y++) {
            if (getPixel(data, x, y) === checkColor) {
                let positiveSurroundingPixels = 0;
                [[-1,-1], [0,-1], [1,-1], [1,0], [1,1], [0,1], [-1,1], [-1,0]].forEach(function(offset) {
                    if (getPixel(data, x + offset[0], y + offset[1]) === surroundingColor) {
                        positiveSurroundingPixels++;
                    }
                });

                if (positiveSurroundingPixels >= minSurroundingPixels) {
                    setPixel(data, x, y, surroundingColor);
                }
            }
        }
    }
}

function hasBorderPixel(data, color) {
    for (let x = 0; x < data.info.width; x++) {
        if (getPixel(data, x, 0) === color || getPixel(data, x, data.info.height - 1) === color) {
            return true;
        }
    }
    for (let y = 0; y < data.info.height; y++) {
        if (getPixel(data, 0, y) === color || getPixel(data, data.info.width - 1, y) === color) {
            return true;
        }
    }

    return false;
}

function decorateBorderPointsWithColors(borderData, reducedBorderData, sharpImageData) {
    for (let i = 0; i < borderData.points.length; i++) {
        let lowestDistance = null;
        for (let j = 0; j < reducedBorderData.points.length; j++) {
            let distance = MathHelper.distanceOfPoints(borderData.points[i], reducedBorderData.points[j]);
            if (lowestDistance === null || distance < lowestDistance.distance) {
                lowestDistance = {
                    point: reducedBorderData.points[j],
                    distance: distance
                };
            }
        }

        if (lowestDistance === null) {
            continue;
        }

        borderData.points[i].color = getColorPixel(sharpImageData, lowestDistance.point.x + reducedBorderData.boundingBox.left, lowestDistance.point.y + reducedBorderData.boundingBox.top);
    }

    return borderData.points;
}

async function getOptimizedImageData(file, threshold, reduction, debug, options) {
    if (typeof options === 'undefined') options = {};
    let targetPieceColor = typeof options['targetPieceColor'] !== 'undefined' ? options['targetPieceColor'] : null;
    let targetBackgroundColor = typeof options['targetBackgroundColor'] !== 'undefined' ? options['targetBackgroundColor'] : null;

    let image = sharp(file);
    let data = await image.threshold(threshold).toColourspace('b-w').raw().toBuffer({resolveWithObject: true});

    //Identify the surrounding area and make it light gray
    scanFill(data, 0, 0, 0xbb);
    if (debug && typeof file === 'string') {
        await sharp(data.data, {raw: data.info}).toFile(file + '.' + reduction + '.step2a.png');
    }

    //Fill everything with black except the surrounding area around the piece
    replaceColor(data, 0xff, 0x00);
    if (debug && typeof file === 'string') {
        await sharp(data.data, {raw: data.info}).toFile(file + '.' + reduction + '.step2b.png');
    }

    //remove aprox. 2 pixels of the piece border to remove some single pixels
    extendArea(data, 0xbb, reduction);
    if (debug && typeof file === 'string') {
        await sharp(data.data, {raw: data.info}).toFile(file + '.' + reduction + '.step2c.png');
    }

    //cut every thin lines (black pixels with at least 6 white pixels around it)
    replaceThinPixels(data, 0x00, 6, 0xbb);
    if (debug && typeof file === 'string') {
        await sharp(data.data, {raw: data.info}).toFile(file + '.' + reduction + '.step2d.png');
    }

    //Remove every black area, which is not the biggest
    removeSmallAreas(data, 0x00, 0x33, 0xbb);
    if (debug && typeof file === 'string') {
        await sharp(data.data, {raw: data.info}).toFile(file + '.' + reduction + '.step2e.png');
    }

    //Check if piece is cut of on an edge
    if (hasBorderPixel(data, 0x33)) {
        throw new Error('Piece is cut of');
    }

    if (targetPieceColor !== null) {
        replaceColor(data, 0x33, targetPieceColor);
    }
    if (targetBackgroundColor !== null) {
        replaceColor(data, 0xbb, targetBackgroundColor);
    }
    
    return data;
}

async function saveTransparentImage(filename, threshold) {
    let sharpImage = sharp(filename);

    let maskData = await getOptimizedImageData(filename, threshold, 0, false, {
        targetPieceColor: 0xff,
        targetBackgroundColor: 0x00
    });

    sharpImage.joinChannel(maskData.data, {raw: maskData.info}).toColourspace('sRGB');

    await sharpImage.toFormat('png').toFile(filename + '.transparent.png');
}

/**
 *
 * @param {string|Buffer} file
 * @param options
 * @returns {Promise}
 */
async function findPieceBorder(file, options) {
    if (typeof options === 'undefined') {
        options = {};
    }
    if (typeof options.debug === 'undefined') {
        options.debug = false;
    }
    if (typeof options.threshold === 'undefined') {
        options.threshold = 200;
    }
    if (typeof options.reduction === 'undefined') {
        options.reduction = 2;
    }
    if (typeof options.returnColorPoints === 'undefined') {
        options.returnColorPoints = false;
    }

    //Get optimized data of the image with dark gray pixels for the piece.
    let data = await getOptimizedImageData(file, options.threshold, options.reduction, options.debug);

    //Get list of all border pixels and decorate them with the colors
    let decoratedBorderPoints = null;
    if (options.returnColorPoints) {
        //Get optimized data of the imaage reduced to the point where the border shows the corect colors
        let colorData = await getOptimizedImageData(file, options.threshold, Math.max(3, options.reduction * 10), options.debug);

        let completeBorderData = getOrderedBorderPoints(data, 0x33, false);
        let completeReducedBorderData = getOrderedBorderPoints(colorData, 0x33, false);
        let sharpImageData = await sharp(file).blur(3).raw().toBuffer({resolveWithObject: true});
        decoratedBorderPoints = decorateBorderPointsWithColors(completeBorderData, completeReducedBorderData, sharpImageData);

        if (options.debug && typeof file === 'string') {
            await sharp(sharpImageData.data, {raw: sharpImageData.info}).toFile(file + '.blur.png');
        }
    }

    //now get the final border pixels of the piece and build path
    let borderData = getOrderedBorderPoints(data, 0x33);

    Paper.setup(new Paper.Size(data.info.width, data.info.height));
    let paperPath = new Paper.Path(borderData.points);

    let files = {};

    if (typeof file === 'string') {
        files.original = path.basename(file);
        if (options.debug) {
            files['step2a'] = path.basename(file) + '.step2a.png';
            files['step2b'] = path.basename(file) + '.step2b.png';
            files['step2c'] = path.basename(file) + '.step2c.png';
            files['step2d'] = path.basename(file) + '.step2d.png';
            files['step2e'] = path.basename(file) + '.step2e.png';

            await saveTransparentImage(file, options.threshold);
            files['transparent'] = path.basename(file) + '.transparent.png';
        }
    }

    return {
        path: paperPath,
        colorPoints: decoratedBorderPoints,
        boundingBox: borderData.boundingBox,
        files: files,
        dimensions: {
            width: data.info.width,
            height: data.info.height
        }
    };
}

module.exports = {
    findPieceBorder: findPieceBorder,
    getOptimizedImageData: getOptimizedImageData
};