# jigsawlutioner
## About
Jigsawlutioner is an algorithm designed to solve a jigsaw puzzle based on images taken by each puzzle piece.

## Features
1. BorderFinder: Parses an image, extracts the border of the piece and returns an array of points describing the border
2. SideFinder: Analyzes the border points and determines, where each of the 4 corners is. Returns 4 arrays containing the border points of each side.
3. PieceAnalyzer: Normalizes the side points (rotate them horizontal, flatten them and divides them in 100 evently distributed points), generates classifiers of each side (is the nop inside or outside, is the nop more left or right, etc.) and returns a piece object.
4. SideMatcher: Returns the probability, two sides can fit together.
5. Solver: Solves the jigsaw puzzle and returns a matrix of where every piece should be correctly rotated.
