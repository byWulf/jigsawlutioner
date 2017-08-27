const port = process.env.PORT || 1100;

const express = require('express');
const app = express();
const http = require('http').Server(app);
const io = require('socket.io')(http);
const Siofu = require('socketio-file-upload');
const path = require('path');
const cv = require('opencv');

const Jigsawlutioner = require('./src/jigsawlutioner');
const OpencvHelper = require('./src/opencvHelper');
const Debug = require('./src/debug');

app.use(express.static('clientTest'));
app.use('/images', express.static('images'));

app.use(Siofu.router);
app.use('/jquery', express.static('node_modules/jquery/dist'));
app.use('/bootstrap', express.static('node_modules/bootstrap/dist'));
app.use('/fontawesome', express.static('node_modules/font-awesome'));
app.use('/tether', express.static('node_modules/tether/dist'));


http.listen(port, () => {
    console.log('Server started on port ' + port);
});

let pendingPiece = null;
let pieces = [];
let groups = [];

let resizeFactor = null;
let nextPieceIndex = 0;

let currentImage = null;
let imageCount = 0;

io.on('connection', (socket) => {
    console.log('user connected');

    const uploader = new Siofu();
    uploader.dir = __dirname + '/images';
    uploader.listen(socket);

    uploader.on('start', (event) => {
        Debug.startTime('1_uploading');

        console.log("upload started");
        io.sockets.emit('state', 'UPLOADING');
    });

    uploader.on('saved', (event) => {
        Debug.endTime('1_uploading');
        Debug.startTime('2_preprocessing');

        console.log("uploading finished, starting preprocessing", event.file.pathName);
        io.sockets.emit('state', 'PREPROCESSING');

        cv.readImage(event.file.pathName, (err, img) => {
            io.sockets.emit('state', 'FINDCONTOURS');

            currentImage = img;
        });
    });

    uploader.on('error', (event) => {
        console.log('Error at uploading', event.message);
        io.sockets.emit('state', 'ERROR', {atStep: 'Uploading', message: event.message});
    });

    socket.on('findContours', (min, max) => {
        let imgCanny = currentImage.copy();
        imgCanny.convertGrayscale();
        imgCanny.canny(min, max);
        imgCanny.dilate(1);
        let contours = imgCanny.findContours('CV_RETR_EXTERNAL', 'CV_CHAIN_APPROX_NONE');

        let imgClone = currentImage.copy();
        imgClone.drawAllContours(contours, [0, 255, 0]);

        let path = './images/contour_' + imageCount++ + '.png';
        imgClone.save(path);

        io.sockets.emit('state', 'FINDCONTOURS', path)
    });

    socket.on('group', (targetGroup) => {
        if (typeof groups[targetGroup] === 'undefined') {
            groups[targetGroup] = [];
        }

        groups[targetGroup].push(pieces[pieces.length - 1].pieceIndex);

        io.sockets.emit('state', 'UPLOAD');
    });

    socket.on('parseCorrect', () => {
        Debug.startTime('4_matching');

        pieces.push(pendingPiece);

        console.log("parse correct, starting matching");

        let matchingPieces = Jigsawlutioner.findMatchingPieces(pendingPiece, pieces);

        let possibleGroups = [];
        for (let groupIndex = 0; groupIndex < groups.length; groupIndex++) {
            for (let matchingPiece of matchingPieces) {
                if (groups[groupIndex].indexOf(matchingPiece.pieceIndex) > -1) {
                    possibleGroups.push(groupIndex);
                    break;
                }
            }
        }

        console.log("matching finished, starting grouping", possibleGroups, groups.length);
        io.sockets.emit('state', 'GROUPING', {possibleGroups: possibleGroups, nextGroupIndex: groups.length});

        Debug.endTime('4_matching');
    });

    socket.on('parseWrong', () => {
        pendingPiece = null;

        io.sockets.emit('state', 'UPLOAD');
    });
});