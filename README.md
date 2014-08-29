OCR (Unfinished)
===

An optical character recognizer that I tried to work on one time but probably won't have time to finish any time soon. Hopefully I will come back to this.

Works by taking an image of all letters and numbers and comparing the pixels of characters from other sample images of text with these letters. Characters in the sample image are assigned a score for each letter or number in the base image, and the letter with the highest score is most likely what that letter in the sample image is.

Still has a bunch of bugs.
- Often misreads lowercase e's with o's
- Base letters are in one type of font
- A bunch more stuff...

Can potentially counter some of these problems with a spellchecker, though I have not figured out how to actually download and use one of the php spellchecker libraries.
