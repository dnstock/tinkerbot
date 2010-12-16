-- phpMyAdmin SQL Dump
-- version 3.2.0.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Dec 07, 2009 at 09:31 AM
-- Server version: 5.1.36
-- PHP Version: 5.3.0

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `msn_tinker`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL COMMENT 'email address',
  `pass` varchar(255) NOT NULL COMMENT 'password',
  `nick` varchar(255) DEFAULT NULL COMMENT 'nickname',
  `is_locked` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'if account is currently in use',
  `unlock_at` datetime DEFAULT NULL COMMENT 'time to automatically unlock account (in case of script timeout)',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Table structure for table `chats`
--

CREATE TABLE IF NOT EXISTS `chats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sent_from` varchar(255) NOT NULL,
  `sent_to` varchar(255) NOT NULL,
  `sent_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` text NOT NULL,
  `wait_after_sending` int(10) unsigned NOT NULL COMMENT 'number of seconds waited before sending next message',
  `message_order_number` int(10) unsigned NOT NULL COMMENT 'number of this message within chat (corresponds to "order" in messages table)',
  `message_total` int(10) unsigned NOT NULL COMMENT 'total number of messages in this chat',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=11 ;

--
-- Table structure for table `config`
--

CREATE TABLE IF NOT EXISTS `config` (
  `debug` varchar(100) NOT NULL COMMENT '''all''=msn+bot output, ''msn''=only msn output, ''none''=no output --- display running output and descriptive error messages',
  `invite_block` int(10) unsigned NOT NULL COMMENT 'amount at which to process new invitations',
  `max_contacts` int(10) unsigned NOT NULL COMMENT 'max number of contacts to add to an account',
  `timeout` int(10) unsigned NOT NULL COMMENT '0=no time limit --- set execution time for script (in seconds) before timing out --- this value might not be enforcible on all servers (ex. php running in safe-mode)',
  `initialize_contacts_from` varchar(100) NOT NULL COMMENT '"database" or "msn" -- where to calculate initial contact count -- should NOT need to change from "db" unless causing issues'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `config`
--

INSERT INTO `config` (`debug`, `invite_block`, `max_contacts`, `timeout`, `initialize_contacts_from`) VALUES
('all', 100, 1000, 0, 'database');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `friend_of` varchar(255) DEFAULT NULL COMMENT 'account which is friend with this contact AND HAS SEEN THEM ONLINE',
  `is_invited` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '1 if they have ever been invited by any account',
  `is_chatted` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'true if contact has received ALL chat messages',
  `is_deleted` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '1 if they have been removed (usually after finishing chat session)',
  `has_been_online` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'true if this contact has ever been spotted online',
  `last_online_at` datetime DEFAULT NULL COMMENT 'last time contact was spotted online (usually coincides with a chat record)',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Table structure for table `invites`
--

CREATE TABLE IF NOT EXISTS `invites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sent_from` varchar(255) NOT NULL,
  `sent_to` varchar(255) NOT NULL,
  `sent_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=15 ;

--
-- Table structure for table `logins`
--

CREATE TABLE IF NOT EXISTS `logins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bot_name` varchar(255) NOT NULL,
  `account` varchar(255) NOT NULL COMMENT 'id of record in account table',
  `contacts` text NOT NULL COMMENT 'list of all contacts for this account at time of login',
  `onlinefriends` text NOT NULL COMMENT 'list of friends that were online for this account at time of login',
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `timeout` int(11) NOT NULL COMMENT 'script execution time limit (in seconds)',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8 ;

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `order` int(10) unsigned NOT NULL COMMENT 'order of message in chat',
  `wait_after_sending` int(10) unsigned NOT NULL COMMENT 'seconds to wait after sending this message before sending the next',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

