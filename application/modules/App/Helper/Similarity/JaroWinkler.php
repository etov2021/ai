<?php

namespace App\Helper\Similarity;

class JaroWinkler
{
    public function compare($str1, $str2)
    {
        return $this->distance($str1, $str2);
    }

    private function distance($s1, $s2)
    {
        // If the strings are equal
        if ($s1 == $s2) {
            return 1.0;
        }

        // Length of two strings
        $len1 = mb_strlen($s1);
        $len2 = mb_strlen($s2);

        // Maximum distance upto which matching
        // is allowed
        $max_dist = (int) floor(max($len1, $len2) / 2) - 1;

        // Count of matches
        $match = 0;

        // Hash for matches
        $hash_s1 = array_fill(0, $len1, 0);
        $hash_s2 = array_fill(0, $len2, 0);

        // Traverse through the first string
        for ($i = 0; $i < $len1; $i++) {
            // Check if there is any matches
            for ($j = max(0, $i - $max_dist); $j < min($len2, $i + $max_dist + 1); $j++) {
                // If there is a match
                if ($s1[$i] == $s2[$j] && $hash_s2[$j] == 0) {
                    $hash_s1[$i] = 1;
                    $hash_s2[$j] = 1;
                    $match++;
                    break;
                }
            }
        }

        // If there is no match
        if ($match == 0) {
            return 0.0;
        }

        // Number of transpositions
        $t = 0;
        $point = 0;

        // Count number of occurrences
        // where two characters match but
        // there is a third matched character
        // in between the indices
        for ($i = 0; $i < $len1; $i++) {
            if ($hash_s1[$i]) {
                // Find the next matched character
                // in second string
                while ($hash_s2[$point] == 0) {
                    $point++;
                }
                if ($s1[$i] != $s2[$point++]) {
                    $t++;
                }
            }
        }

        $t /= 2;

        // Return the Jaro Similarity
        return (($match) / ($len1) + ($match) / ($len2) + ($match - $t) / ($match)) / 3.0;
    }
}
