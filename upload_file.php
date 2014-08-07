<?php

// get any errors
ini_set('display_errors', true);
error_reporting(-1);

if ($_FILES["file"]["error"] > 0) {
    echo "Error: " . $_FILES["file"]["error"] . "<br>";
}
else {
    
    $start = round(microtime(true)*1000);
    
    $WHITE_LIMIT = 220;
    $PINK = array(255, 105, 180);
    $LETTERS = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $LETTERS = str_split($LETTERS);
    
    $img = layoutLetters($_FILES["file"], true);
    $img2 = layoutLetters($_FILES["compare"], false);
    $base_letter_map = generateLetters($_FILES["file"], true);
    $image_letter_map = generateLetters($_FILES["compare"], false);
    
    
    $displayType = $_POST["display"];
    if ($displayType == "none"){
        echo "Upload: " . $_FILES["file"]["name"] . "<br>";
        echo "Type: " . $_FILES["file"]["type"] . "<br>";
        echo "Size: " . ($_FILES["file"]["size"] / 1024) . " kB<br>";
        echo "Stored in: " . $_FILES["file"]["tmp_name"] . "<br><br>";

        echo "Upload: " . $_FILES["compare"]["name"] . "<br>";
        echo "Type: " . $_FILES["compare"]["type"] . "<br>";
        echo "Size: " . ($_FILES["compare"]["size"] / 1024) . " kB<br>";
        echo "Stored in: " . $_FILES["compare"]["tmp_name"] . "<br><br>";
        
        // ADD ADDITIONAL STUFF HERE

    }
    else {
        if ($displayType == "Image1") display($img);
        else if ($displayType == "Image2") display($img2);
        else if ($displayType == "lettermap1"){
            $val = $_POST["lettermapnumber1"];
            if ($val >= 0) display($base_letter_map[$val]);
            else {
                $w = 0;
                $h = 0;
                $x = 0;
                foreach($base_letter_map as $letter){
                    $w += imagesx($letter);
                    $h = max($h,imagesy($letter));
                }
                $img = imagecreatetruecolor($w, $h);
                for ($i = 0; $i < count($base_letter_map); $i++){
                    $letter = $base_letter_map[$i];
                    imagecopyresized($img, $letter, $x, 0, 0, 0, imagesx($letter), $h, imagesx($letter), imagesy($letter));
                    $x += imagesx($letter);
                }
                display($img);
            }
        }
        else if ($displayType == "lettermap2"){
            $i = $_POST["lettermapnumber2"];
            display($image_letter_map[$i]);
        }
        else if ($displayType == "lettercompare"){
            $i1 = $_POST["lettermapnumbercompare1"];
            $i2 = $_POST["lettermapnumbercompare2"];
            if ($i1 < 0 || $i2 < 0){
                $i1 = -$i1;
                $i2 = -$i2;
                $letter1 = $base_letter_map[$i1];
                $letter2 = $image_letter_map[$i2];
                $comparison = compareLetters($letter2, $letter1);
                echo "compare '" . $GLOBALS["LETTERS"][$i1] . "' against letter " . $i2 . " in compare string<br>";
                echo $comparison[1];
            }
            else {
                $img = compareLetters($image_letter_map[$i2],$base_letter_map[$i1])[0];
                display($img);
            }
        }
        else if ($displayType == "scan"){
            $s0 = "";
            $s1 = "";
            $s2 = "";
            foreach ($image_letter_map as $letter1){
                $val = 0;
                $char = 0;
                for ($i = 0; $i < count($base_letter_map); $i++){
                    $letter2 = $base_letter_map[$i];
                    $comparison = compareLetters($letter1, $letter2);
                    if ($comparison[1] > $val){
                        $val = max($val, $comparison[1]);
                        $char = $i;
                    }
                }
                $s0 .= $GLOBALS["LETTERS"][$char] . " ";
                $s1 .= $char . " ";
                $s2 .= $val . " ";
            }
            echo $s0 . "<br>";
            echo $s1 . "<br>";
            echo $s2 . "<br>";
        }
    }
}

function display($img){
    header("Content-Type: image/png");
    imagepng($img);
    imagedestroy($img);
}

function avg($a){
    return array_sum($a)/count($a);
}

function getRGB($rgb){
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    return array($r,$g,$b);
}

function getRGBAtPixel($img, $x, $y){
    return getRGB(imagecolorat($img, $x, $y));
}

function getColorVal($img, $rgb){
    return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
}

function setupImg($file, $darken, $w, $h, $WHITE_LIMIT){
   
    $im = imagecreatefrompng($file["name"]);
    $img = imagecreatetruecolor($w,$h);
    
    for ($y = 0; $y < $h; $y++){
        for ($x = 0; $x < $w; $x++){
            $rgb = getRGBAtPixel($im, $x, $y);
            $r = $rgb[0];
            $g = $rgb[1];
            $b = $rgb[2];
            
            //imagesetpixel($img, $x, $y, getColorVal($img, $rgb));
            if ($darken && avg($rgb) < $WHITE_LIMIT){
                imagesetpixel($img, $x, $y, getColorVal($img, array(0,0,0)));
            }
            else if (!$darken && $rgb != array(0,0,0)){
                imagesetpixel($img, $x, $y, getColorVal($img, array(255,255,255)));
            }
            else imagesetpixel($img, $x, $y, getColorVal($img, $rgb));
        }
    }
    return $img;
}

function layoutLetters($file, $darken){
    $WHITE_LIMIT = $GLOBALS["WHITE_LIMIT"];
    $PINK = $GLOBALS["PINK"];
    
    $dims = getimagesize($file["name"]);
    $w = $dims[0];
    $h = $dims[1];
    
    $img = setupImg($file, $darken, $w, $h, $WHITE_LIMIT);
    
    // process the array of rgbs
    
    // split the picture into characters by adding markers
    // at every point between the characters which are spearated by white space
   
    // mark white horizontal rows
    $row_marker = array();
    $row_positions = array(); // save the row positions
    $no_white_flag = false;
    for ($y = 0; $y < $h; $y++){
        $mark = true;
        for ($x = 0; $x < $w; $x++){
            $avg = avg(getRGBAtPixel($img, $x, $y));
            if ($avg < $WHITE_LIMIT){
                $mark = false;
            }
        }
        if (!$mark && !$no_white_flag){
            $no_white_flag = true;
            array_push($row_positions,$y);
        }
        else if ($mark && $no_white_flag){
            $no_white_flag = false;
            array_push($row_positions,$y-1);
        }
        array_push($row_marker, $mark);
    }
    
    $row_positions2 = array();
    for ($i = 0; $i < count($row_positions); $i += 2){
        array_push($row_positions2,
                array($row_positions[$i],$row_positions[$i+1])
                );
    }
    
    // color white rows pink
    for ($y = 0; $y < $h; $y++){
        for ($x = 0; $x < $w; $x++){
            if ($row_marker[$y]){
                $color = imagecolorallocate($img, $PINK[0], $PINK[1], $PINK[2]);
                imagesetpixel($img, $x, $y, $color);
            }
        }
    }
    
    foreach ($row_positions2 as $row_position){
        for ($x = 0; $x < $w; $x++){
            $mark = true;
            for ($y = $row_position[0]; $y <= $row_position[1]; $y++){
                $avg = avg(getRGBAtPixel($img, $x, $y));
                if ($avg < $WHITE_LIMIT){
                    $mark = false;
                }
            }
            if ($mark){
                for ($y = $row_position[0]; $y <= $row_position[1]; $y++){
                    $color = imagecolorallocate($img, $PINK[0], $PINK[1], $PINK[2]);
                    imagesetpixel($img, $x, $y, $color);
                }
            }
        }
    }
    return $img;
}

function generateLetters($file, $darken){
    $WHITE_LIMIT = $GLOBALS["WHITE_LIMIT"];
    $PINK = $GLOBALS["PINK"];
    
    $dims = getimagesize($file["name"]);
    $w = $dims[0];
    $h = $dims[1];
    
    $img = layoutLetters($file, $darken);
    
    $letter_maps = filterOutPink($img, $w, $h);
    for ($i = 0; $i < count($letter_maps); $i++){
        $letter_maps[$i] = cleanUpLetter($letter_maps[$i]);
    }
    
    return $letter_maps;
}

function compareLetters($letter1, $letter2){ // letter 2 is the base; letter 1 is the compare
    $w1 = imagesx($letter1);
    $h1 = imagesy($letter1);
    $w2 = imagesx($letter2);
    $h2 = imagesy($letter2);
    
    $img = imagecreatetruecolor($w1, $h1);
    imagecopyresized($img, $letter2, 0, 0, 0, 0, $w1, $h1, $w2, $h2);
    
    $PINK = array(255, 105, 180);
    $BLACK_LIMIT = 200;
    $WHITE_LIMIT = $GLOBALS["WHITE_LIMIT"];
    $black_pixel_count = 0;
    $black_overlay_count = 0;
    for ($y = 0; $y < $h1; $y++){
        for ($x = 0; $x < $w1; $x++){
            $rgb1 = getRGBAtPixel($letter1, $x, $y);
            $rgb2 = getRGBAtPixel($img, $x, $y);
            
            // count 1 for every black marks on either img
            /*if (avg($rgb1) < $BLACK_LIMIT || avg($rgb2) < $BLACK_LIMIT){
                $black_pixel_count++;
            }*/
            
            // add 1 for every black marks that match on both imgs
            /*if (avg($rgb1) < $BLACK_LIMIT && avg($rgb2) < $BLACK_LIMIT){
                $black_overlay_count++;
                $black_pixel_count++;
            }*/
            if ($rgb1 != array(255,255,255) && avg($rgb2) < $BLACK_LIMIT){
                $black_overlay_count++;
                $black_pixel_count++;
            }
            
            // subtract 1 for every black mark on base over white space in compare
            /*if (avg($rgb1) < $BLACK_LIMIT && avg($rgb2) > $WHITE_LIMIT){
                $black_overlay_count -= 1;
            }*/
            
            // subtract 1 for every black mark on compare over white space in base
            /*if (avg($rgb2) < $BLACK_LIMIT && avg($rgb1) > $WHITE_LIMIT){
                $black_overlay_count -= 1;
            }*/
            
            // color the base letter pink and overlay onto compare
            if (avg($rgb1) < $BLACK_LIMIT && avg($rgb2) < $BLACK_LIMIT){
                $color = imagecolorallocate($img, $PINK[0], $PINK[1], $PINK[2]);
                imagesetpixel($img, $x, $y, $color);
            }
        }
    }
    
    if ($black_overlay_count < 0) $black_overlay_count = 0;
    
    //return array($img, $black_overlay_count/$black_pixel_count);
    return array($img, $black_overlay_count);
}

function cleanUpLetter($letter){
    $WHITE_LIMIT = $GLOBALS["WHITE_LIMIT"];
    $PINK = $GLOBALS["PINK"];
    
    $w = imagesx($letter);
    $h = imagesy($letter);
    
    for ($y = 0; $y < $h; $y++){
        $mark = true;
        for ($x = 0; $x < $w; $x++){
            $rgb = getRGBAtPixel($letter, $x, $y);
            if (avg($rgb) < $WHITE_LIMIT) $mark = false;
        }
        if ($mark){
            for ($x = 0; $x < $w; $x++){
                $color = imagecolorallocate($letter, $PINK[0], $PINK[1], $PINK[2]);
                imagesetpixel($letter, $x, $y, $color);
            }
        }
    }
    for ($x = 0; $x < $w; $x++){
        $mark = true;
        for ($y = 0; $y < $h; $y++){
            $rgb = getRGBAtPixel($letter, $x, $y);
            if (avg($rgb) < $WHITE_LIMIT && $rgb != $PINK) $mark = false;
        }
        if ($mark){
            for ($x = 0; $x < $w; $x++){
                $color = imagecolorallocate($letter, $PINK[0], $PINK[1], $PINK[2]);
                imagesetpixel($letter, $x, $y, $color);
            }
        }
    }
    
    $letters = filterOutPink($letter, $w, $h);
    return $letters[0];
}

function filterOutPink($img, $w, $h){
    $PINK = $GLOBALS["PINK"];
    
    // get upper left and bottom right corners for each letter
    $letter_positions = array();
    $xtemp = -1;
    $ytemp = -1;
    for ($y = 0; $y < $h; $y++){
        if ($y > $ytemp){
            $xtemp = -1; // reset xtemp
            for ($x = 0; $x < $w; $x++){
                if ($x > $xtemp){
                    if (getRGBAtPixel($img, $x, $y) != $PINK){
                        $corners = array();
                        array_push($corners, array($x, $y));
                        $xtemp = $x;
                        while (getRGBAtPixel($img, ++$xtemp, $y) != $PINK){
                            // increase xtemp until reach pink
                            if($xtemp >= $w-1){
                                $xtemp++;
                                break;
                            }
                        }
                        $xtemp--;
                        $ytemp = $y;
                        while(getRGBAtPixel($img, $xtemp, ++$ytemp) != $PINK){
                            // increase ytemp until reach pink again
                            if($ytemp >= $h-1){
                                $ytemp++;
                                break;
                            }
                        }
                        $ytemp--;
                        array_push($corners, array($xtemp, $ytemp));
                        array_push($letter_positions, $corners);
                    }
                }
            }
        }
    }
    
    /*
    letter_positions:
    [
      [ [char1_x1, char1_y1], [char1_x2, char1_y2] ],
      [ [char2_x1, char2_y1], [char2_x2, char2_y2] ],
      ...
    ]
    */
    
    // create separate image resources for each letter
    $letter_maps = array();
    for ($i = 0; $i < count($letter_positions); $i++){
        $letter = $letter_positions[$i];
        $p1 = $letter[0];
        $p2 = $letter[1];
        $width = $p2[0] - $p1[0] + 1;
        $height = $p2[1] - $p1[1] + 1;
        $char = imagecreatetruecolor($width, $height);
        imagecopyresized($char, $img, 0, 0, $p1[0], $p1[1], $width, $height, $width, $height);
        array_push($letter_maps, $char);
    }
    
    return $letter_maps;
}

?>