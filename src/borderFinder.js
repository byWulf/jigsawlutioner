const path = require('path');
const sharp = require('sharp');
const Paper = require('paper');

const PathHelper = require('./pathHelper');

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

function removeSmallAreas(data, areaColor, biggestAreaColor, clearColor) {
    let sizes = getSizes(data, areaColor, biggestAreaColor);
    if (sizes.length === 0) {
        throw Error('No areas found');
    }
    sizes.sort((a, b) => {
        return b.size - a.size;
    });
    for (let i = 1, length = sizes.length; i < length; i++) {
        scanFill(data, sizes[i].x, sizes[i].y, clearColor + 1);
    }
    replaceColor(data, clearColor + 1, clearColor);
}

function getOrderedBorderPoints(data, areaColor) {
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
                    points: PathHelper.simplifyPoints(points),
                    boundingBox: boundingBox
                };
            }
        }
    }

    throw new Error('No point found');
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
        for (let x = -extendSize; x <= extendSize; x++) {
            for (let y = -extendSize; y <= extendSize; y++) {
                if (Math.round(Math.sqrt(x * x + y * y)) <= 2) {
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

/**
 *
 * @param {string|Buffer} file
 * @param options
 * @returns {Promise}
 */
function findPieceBorder(file, options) {
    if (typeof options === 'undefined') {
        options = {};
    }
    if (typeof options.debug === 'undefined') {
        options.debug = false;
    }
    if (typeof options.threshold === 'undefined') {
        options.threshold = 200;
    }


    return new Promise((resolve, reject) => {
        let data = null;
        let image = sharp(file);
        image.threshold(options.threshold).toColourspace('b-w').raw().toBuffer({resolveWithObject: true}).then((resultData) => {

            data = resultData;

            //Identify the surrounding area and make it light gray
            scanFill(data, 0, 0, 0xbb);
            if (options.debug && typeof file === 'string') return sharp(data.data, {raw: data.info}).toFile(file + '.step2a.png');
        }).then(() => {

            //Fill everything with black except the surrounding area around the piece
            replaceColor(data, 0xff, 0x00);
            if (options.debug && typeof file === 'string') return sharp(data.data, {raw: data.info}).toFile(file + '.step2b.png');
        }).then(() => {

            //remove aprox. 2 pixels of the piece border to remove some single pixels
            extendArea(data, 0xbb, 1);
            if (options.debug && typeof file === 'string') return sharp(data.data, {raw: data.info}).toFile(file + '.step2c.png');
        }).then(() => {
            //cut every thin lines (black pixels with at least 6 white pixels around it)
            replaceThinPixels(data, 0x00, 6, 0xbb);
            if (options.debug && typeof file === 'string') return sharp(data.data, {raw: data.info}).toFile(file + '.step2d.png');
        }).then(() => {
            //Remove every black area, which is not the biggest
            removeSmallAreas(data, 0x00, 0x33, 0xbb);
            if (options.debug && typeof file === 'string') return sharp(data.data, {raw: data.info}).toFile(file + '.step2e.png');
        }).then(() => {
            //Check if piece is cut of on an edge
            if (hasBorderPixel(data, 0x33)) {
                throw new Error('Piece is cut of');
            }

            //now get the final border pixels of the piece
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
                }
            }

            resolve({
                path: paperPath,
                boundingBox: borderData.boundingBox,
                files: files,
                dimensions: {
                    width: data.info.width,
                    height: data.info.height
                }
            });

        }).catch((err) => {
            reject(err);
        });
    })
}

module.exports = {
    findPieceBorder: findPieceBorder
};