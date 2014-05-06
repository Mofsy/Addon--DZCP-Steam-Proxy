-- phpMyAdmin SQL Dump
-- version 4.1.12
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1:3306
-- Erstellungszeit: 03. Mai 2014 um 12:16
-- Server Version: 5.5.34-MariaDB-log
-- PHP-Version: 5.5.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Datenbank: `steamproxy`
--

CREATE TABLE IF NOT EXISTS `steam_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `steamid` varchar(200) NOT NULL DEFAULT '',
  `communityid` varchar(64) NOT NULL DEFAULT '',
  `data` blob,
  `data_api` mediumblob,
  `time_data` int(20) NOT NULL DEFAULT '0',
  `time_data_api` int(20) NOT NULL DEFAULT '0',
  `update_fails` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `steamid` (`steamid`),
  KEY `time_data` (`time_data`),
  KEY `time_data_api` (`time_data_api`)
) DEFAULT CHARSET=latin1;