const paper = require('paper-jsdom');
const cv = require('opencv');
const Jimp = require('jimp');

const MathHelper = require('./mathHelper');

/**
 * Draws an outlined cross onto the opencv matrix.
 *
 * @param img
 * @param x
 * @param y
 */
function drawOutlinedCross(img, x, y) {
    for (let data of [{x: -1, y: 0, color: [0,0,0]}, {x: 1, y: 0, color: [0,0,0]}, {x: 0, y: -1, color: [0,0,0]}, {x: 0, y: 1, color: [0,0,0]}, {x: 0, y: 0, color: [255,255,255]}, {x: 0, y: 0, color: [255,255,255]}]) {
        img.line([x - 10 + data.x, y - 10 + data.y], [x + 10 + data.x, y + 10 + data.y], data.color);
        img.line([x + 10 + data.x, y - 10 + data.y], [x - 10 + data.x, y + 10 + data.y], data.color);
    }
}

/**
 * Draws an outlined text onto the opencv matrix.
 *
 * @param img
 * @param x
 * @param y
 * @param text
 */
function drawOutlinedText(img, x, y, text) {
    for (let data of [{x: -1, y: 0, color: [0,0,0]}, {x: 1, y: 0, color: [0,0,0]}, {x: 0, y: -1, color: [0,0,0]}, {x: 0, y: 1, color: [0,0,0]}, {x: 0, y: 0, color: [255,255,255]}, {x: 0, y: 0, color: [255,255,255]}]) {
        img.putText(text, x + data.x, y + data.y, 'HERSEY_PLAIN', data.color, 2, 1);
    }
}

function findContours(img, cannyLow, cannyHigh) {
    if (cannyLow < 20) throw new Error('cannyLow too low');
    if (cannyLow > 400) throw new Error('cannyLow too high');

    let imgCanny = img.copy();
    imgCanny.convertGrayscale();
    imgCanny.canny(cannyLow, cannyHigh);
    imgCanny.dilate(1);
    let contours = imgCanny.findContours('CV_RETR_EXTERNAL', 'CV_CHAIN_APPROX_NONE');

    if (contours.size() === 0) throw new Error('Could not find any surface on the given image.');

    let maxArea = null;
    for (let i = 0; i < contours.size(); i++) {
        if (maxArea === null || contours.area(i) > maxArea.size) {
            maxArea = {
                size: contours.area(i),
                index: i
            };
        }
    }

    //Sometimes the cannyLow value has to be adjusted to correctly recognize the puzzle piece
    let arcLength = contours.arcLength(maxArea.index, true);
    let minAreaRect = contours.minAreaRect(maxArea.index);

    if (arcLength > (minAreaRect.size.height * 2 + minAreaRect.size.width * 2) * 2) {
        return findContours(img, cannyLow * 0.6, cannyHigh);
    }
    if (arcLength < (minAreaRect.size.height * 2 + minAreaRect.size.width * 2) * 0.6) {
        return findContours(img, cannyLow * 1.3, cannyHigh);
    }

    //Generate points and path
    let boundingRect = contours.boundingRect(maxArea.index);
    paper.setup(new paper.Size(img.width(), img.height()));
    let points = [];
    let path = new paper.Path();

    for (let i = 0; i < contours.cornerCount(maxArea.index); ++i) {
        let point = contours.point(maxArea.index, i);

        points.push(point);
        path.add(new paper.Point(point.x, point.y));
    }

    return {
        image: img,
        boundingRect: boundingRect,
        minAreaRect: minAreaRect,
        points: points,
        path: path,
        drawOnImage: (image, color, thickness, lineType, maxLevel) => image.drawContour(contours, maxArea.index, color, thickness, lineType, maxLevel, [0, 0])
    };
}

let resizeFactor = null;

function prepareImage(filename) {
    return new Promise((fulfill, reject) => {
        cv.readImage(filename, (err, img) => {
            let contour = null;
            try {
                contour = findContours(img, 200, 300);
            } catch (err) {
                reject(err);
            }

            if (resizeFactor === null) {
                for (let i = 0; i < contour.minAreaRect.points.length; i++) {
                    resizeFactor += MathHelper.distanceOfPoints(contour.minAreaRect.points[i], contour.minAreaRect.points[(i + 1) % contour.minAreaRect.points.length]);
                }
                resizeFactor /= contour.minAreaRect.points.length;
            }

            Jimp.read(filename).then((image) => {
                image
                .crop(
                    contour.boundingRect.x - contour.boundingRect.width * 0.1,
                    contour.boundingRect.y - contour.boundingRect.height * 0.1,
                    contour.boundingRect.width * 1.2,
                    contour.boundingRect.height * 1.2
                )
                .scale(250 / resizeFactor)
                .write(filename.replace('.jpg', '_preprocessed.jpg'), () => {
                    fulfill(filename.replace('.jpg', '_preprocessed.jpg'));
                });
            });
        });
    });
}

module.exports = {
    findContours: findContours,
    drawOutlinedCross: drawOutlinedCross,
    drawOutlinedText: drawOutlinedText,
    prepareImage: prepareImage,
};