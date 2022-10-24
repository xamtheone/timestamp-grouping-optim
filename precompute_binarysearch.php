<?php

const DATE_FORMAT = 'Y-m-d';
const DATE_INTERVAL = 'P1D';
const NB_VALUES = 10000000;
const CACHE_FILE = 'cache'.NB_VALUES.'.txt';


function generateDates($nb, $cacheFile) {
  echo "Génération du fichier de cache $cacheFile\n";
  $from = strtotime('2022-01-01 00:00:00');
  $to = strtotime('2022-12-31 23:59:59');
  
  $timestamps = [];
  for ($i=0; $i < $nb; $i++) { 
    $timestamps[] = random_int($from, $to); 
  }

  file_put_contents($cacheFile, serialize($timestamps));

  return $timestamps;
}

function getDates($nb, $cacheFile) {
  if (!file_exists($cacheFile)) {
    return generateDates($nb, $cacheFile);
  }

  echo "Lecture du fichier de cache $cacheFile\n";
  return unserialize(file_get_contents($cacheFile));
}

function testReadEveryDate(array $timestamps) {
  $results = [];

  foreach ($timestamps as $timestamp) {
    $date = date(DATE_FORMAT, $timestamp);
    if (!isset($results[$date])) {
      $results[$date] = 1;
    }
    else {
      ++$results[$date];
    }
  }

  return $results;
}

function testSearchKnownDates(array $timestamps, $interval, $dateFormat) {
  // tableau de timestamp => date formatée
  $dates = getFormattedDates($timestamps, $interval, $dateFormat);
  // timestamps uniquement pour la recherche binaire
  $keys = array_keys($dates);
  // le tableau doit être trié pour la recherche binaire
  sort($keys);

  $results = [];

  foreach ($timestamps as $timestamp) {
    $date = binarySearchOnIntervals($timestamp, $dates, $keys);
    if (!isset($results[$date])) {
      $results[$date] = 1;
    }
    else {
      ++$results[$date];
    }
  }

  return $results;
}

function getFormattedDates($timestamps, $interval, $dateFormat) {
  $start = new DateTime();
  $end = new DateTime();
  $start->setTimestamp(min($timestamps));
  $start->modify('midnight');
  $end->setTimestamp(max($timestamps));
  $end->modify('tomorrow midnight');
  $dateInterval = new DateInterval($interval);
  $datePeriod = new DatePeriod($start, $dateInterval, $end);
  $dates = [];
  foreach ($datePeriod as $date) {
    $dates[$date->getTimestamp()] = $date->format($dateFormat);
  }

  return $dates;
}

function binarySearchOnIntervals($timestamp, $dates, $sortedTimestamps) {
  $high = count($dates) - 1;
  $low = 0;

  while ($high - $low > 1) {
    $position = floor(($high + $low) / 2);
    $compared_timestamp = $sortedTimestamps[$position];

    if ($timestamp > $compared_timestamp) {
      // On compare l'intervale avec la valeur suivante et on retourne le résultat si la valeur cherché est borné
      if ($timestamp < $sortedTimestamps[$position + 1]) {
        return $dates[$compared_timestamp];
      }

      $low = $position + 1;
    }
    else {
      $high = $position;
    }
  }

  if ($timestamp >= $sortedTimestamps[$low] && $timestamp < $sortedTimestamps[$high]) {
    return $dates[$sortedTimestamps[$low]];
  }

  if ($timestamp > $sortedTimestamps[$high] && $high == count($dates) - 1) {
    return $dates[$sortedTimestamps[$high]];
  }

  if ($timestamp == $sortedTimestamps[$high]) {
    return $dates[$sortedTimestamps[$high]];
  }

  throw new Exception("Error finding $timestamp during search. previous low = {$sortedTimestamps[$low-1]}, low = {$sortedTimestamps[$low]}, high = {$sortedTimestamps[$high]}");
}

$timestamps = getDates(NB_VALUES, CACHE_FILE);

echo number_format(count($timestamps))." de timestamps\n";

echo "Calcul de la date de chaque timestamp\n";

$start = hrtime(true);
$results = testReadEveryDate($timestamps);
$end = hrtime(true);

echo number_format(($end - $start) / 1000000000, 3)."s - ".number_format(memory_get_peak_usage() / 1024**2)."Mo\n";

echo "Recherche binaire sur un tableau de dates connues\n";

$start = hrtime(true);
$result = testSearchKnownDates($timestamps, DATE_INTERVAL, DATE_FORMAT);
$end = hrtime(true);

echo number_format(($end - $start) / 1000000000, 3)."s - ".number_format(memory_get_peak_usage() / 1024**2)."Mo\n";