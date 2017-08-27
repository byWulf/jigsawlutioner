const port = process.env.PORT || 1100;

const express = require('express');
const app = express();
const http = require('http').Server(app);
const io = require('socket.io')(http);
const Siofu = require('socketio-file-upload');
const MongoClient = require('mongodb').MongoClient;
const path = require('path');

const Jigsawlutioner = require('./src/jigsawlutioner');
const OpencvHelper = require('./src/opencvHelper');
const Debug = require('./src/debug');
const BorderFinder = require('./src/borderFinder');

app.use(express.static('client'));
app.use('/images', express.static('images'));

app.use(Siofu.router);
app.use('/jquery', express.static('node_modules/jquery/dist'));
app.use('/bootstrap', express.static('node_modules/bootstrap/dist'));
app.use('/fontawesome', express.static('node_modules/font-awesome'));
app.use('/tether', express.static('node_modules/tether/dist'));
app.use('/paper', express.static('node_modules/paper/dist'));

MongoClient.connect('mongodb://localhost:27017/jigsawlutioner').then((db) => {
    let collection = db.collection('sets');

    http.listen(port, () => {
        console.log('Server started on port ' + port);
    });

    let pendingPiece = null;
    let pieces = [];
    let groups = [];

    let resizeFactor = null;

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

            BorderFinder.findPieceBorder(event.file.pathName).then((borders) => {
                Debug.endTime('2_preprocessing');
                Debug.startTime('3_parsing');

                console.log("preprocessing finished, starting parsing");
                io.sockets.emit('state', 'PARSING', path.basename(event.file.pathName));

                Jigsawlutioner.analyzeBorders(borders).then((piece) => {
                    Debug.endTime('3_parsing');

                    pendingPiece = piece;

                    console.log("parsing finished, asking if correct");
                    let frontendPiece = {
                        diffs: piece.diffs,
                        pieceIndex: piece.pieceIndex,
                        sides: piece.sides,
                        filename: path.basename(event.file.pathName),
                        maskFilename: path.basename(event.file.pathName)
                    };
                    io.sockets.emit('state', 'CHECKPARSE', frontendPiece);

                    Debug.output();
                }).catch((err) => {
                    console.log(err);
                    io.sockets.emit('state', 'ERROR', {atStep: 'Parsing', message: err});
                });
            }).catch((err) => {
                console.log('Error at preprocessing', err);
                io.sockets.emit('state', 'ERROR', {atStep: 'Preprocessing', message: err});
            });

            /*OpencvHelper.prepareImage(event.file.pathName, resizeFactor).then((preparationData) => {
                Debug.endTime('2_preprocessing');
                Debug.startTime('3_parsing');

                resizeFactor = preparationData.resizeFactor;

                console.log("preprocessing finished, starting parsing");
                io.sockets.emit('state', 'PARSING', path.basename(preparationData.newFilename));

                Jigsawlutioner.analyzeFile(preparationData.newFilename).then((piece) => {
                    Debug.endTime('3_parsing');

                    pendingPiece = piece;

                    console.log("parsing finished, asking if correct");
                    let frontendPiece = {
                        pieceIndex: piece.pieceIndex,
                        sides: piece.sides,
                        filename: path.basename(piece.filename),
                        maskFilename: path.basename(piece.maskFilename)
                    };
                    io.sockets.emit('state', 'CHECKPARSE', frontendPiece);

                    Debug.output();
                }).catch((err) => {
                    console.log(err);
                    io.sockets.emit('state', 'ERROR', {atStep: 'Parsing', message: err});
                });
            }).catch((err) => {
                console.log('Error at preprocessing', err);
                io.sockets.emit('state', 'ERROR', {atStep: 'Preprocessing', message: err});
            });*/
        });

        uploader.on('error', (event) => {
            console.log('Error at uploading', event.message);
            io.sockets.emit('state', 'ERROR', {atStep: 'Uploading', message: event.message});
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
}).catch((err) => {
    console.log("Could not connect to mongoDB: ", err);
});


