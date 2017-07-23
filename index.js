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

app.use(express.static('client'));
app.use('/images', express.static('images'));

app.use(Siofu.router);
app.use('/jquery', express.static('node_modules/jquery/dist'));
app.use('/bootstrap', express.static('node_modules/bootstrap/dist'));
app.use('/fontawesome', express.static('node_modules/font-awesome'));
app.use('/tether', express.static('node_modules/tether/dist'));

MongoClient.connect('mongodb://localhost:27017/jigsawlutioner').then((db) => {
    let collection = db.collection('sets');

    http.listen(port, () => {
        console.log('Server started on port ' + port);
    });

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
            socket.emit('state', 'UPLOADING');
        });

        uploader.on('saved', (event) => {
            Debug.endTime('1_uploading');
            Debug.startTime('2_preprocessing');

            console.log("uploading finished, starting preprocessing", event.file.pathName);
            socket.emit('state', 'PREPROCESSING');

            OpencvHelper.prepareImage(event.file.pathName, resizeFactor).then((preparationData) => {
                Debug.endTime('2_preprocessing');
                Debug.startTime('3_parsing');

                resizeFactor = preparationData.resizeFactor;

                console.log("preprocessing finished, starting parsing");
                socket.emit('state', 'PARSING', path.basename(preparationData.newFilename));

                Jigsawlutioner.analyzeFile(preparationData.newFilename).then((piece) => {
                    Debug.endTime('3_parsing');
                    Debug.startTime('4_matching');

                    pieces.push(piece);

                    console.log("parsing finished, starting matching");
                    socket.emit('state', 'MATCHING', path.basename(piece.filename));

                    let matchingPieces = Jigsawlutioner.findMatchingPieces(piece, pieces);

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
                    socket.emit('state', 'GROUPING', {possibleGroups: possibleGroups, nextGroupIndex: groups.length});

                    Debug.endTime('4_matching');
                    Debug.output();
                }).catch((err) => {
                    console.log(err);
                    socket.emit('state', 'ERROR', {atStep: 'Parsing', message: err});
                });
            }).catch((err) => {
                console.log('Error at preprocessing', err);
                socket.emit('state', 'ERROR', {atStep: 'Preprocessing', message: err});
            });
        });

        uploader.on('error', (event) => {
            console.log('Error at uploading', event.message);
            socket.emit('state', 'ERROR', {atStep: 'Uploading', message: event.message});
        });

        socket.on('group', (targetGroup) => {
            if (targetGroup === 'wrong') {
                pieces.pop();
                socket.emit('state', 'UPLOAD');
                return;
            }

            if (typeof groups[targetGroup] === 'undefined') {
                groups[targetGroup] = [];
            }

            groups[targetGroup].push(pieces[pieces.length - 1].pieceIndex);

            socket.emit('state', 'UPLOAD');
        });
    });
}).catch((err) => {
    console.log("Could not connect to mongoDB: ", err);
});


