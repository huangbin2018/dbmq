-- 创建相关是数据表
CREATE TABLE `mq_consumer` (
  `consumerid` int(11) NOT NULL AUTO_INCREMENT,
  `consumer_key` varchar(64) NOT NULL DEFAULT '' COMMENT '消费者key',
  `channel` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道',
  `tag_type` tinyint(3) NOT NULL DEFAULT '0' COMMENT 'tag类型(0全部，1自选)',
  `processid` int(11) DEFAULT '0' COMMENT '进程id',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '创建日期',
  `update_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间戳',
  `status` tinyint(4) DEFAULT '0' COMMENT '0等待运行，1，运行中，2停止',
  `max_sys_load_average` decimal(18,4) DEFAULT '0.5000' COMMENT '最大系统负载率',
  PRIMARY KEY (`consumerid`),
  KEY `consumer_key` (`consumer_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息队列消费者表';

CREATE TABLE `mq_consumer_tag` (
  `ct_id` int(11) NOT NULL AUTO_INCREMENT,
  `consumer_key` varchar(64) NOT NULL DEFAULT '' COMMENT '消费者key',
  `channel` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道',
  `tag` varchar(64) NOT NULL DEFAULT '',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '创建日期',
  PRIMARY KEY (`ct_id`),
  UNIQUE KEY `unique_c_t` (`consumer_key`,`tag`) USING BTREE,
  KEY `channel_tag` (`channel`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消费者tag表';

CREATE TABLE `mq_message` (
  `messageid` bigint(18) NOT NULL AUTO_INCREMENT,
  `channel` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道',
  `tag` varchar(64) NOT NULL DEFAULT '' COMMENT 'tag',
  `consumer_key` varchar(64) NOT NULL DEFAULT '' COMMENT 'consumer key',
  `key` varchar(64) NOT NULL DEFAULT '' COMMENT '消息key',
  `body` longtext NOT NULL COMMENT '消息体',
  `status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '状态（0待消费，1消费失败，消费成功会写入到mq_message_log表）',
  `exception_times` int(11) NOT NULL DEFAULT '0' COMMENT '异常次数',
  `fail_times` int(11) NOT NULL DEFAULT '0' COMMENT '失败次数',
  `note` text NOT NULL COMMENT '备注',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '时间戳',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '创建日期',
  PRIMARY KEY (`messageid`),
  KEY `idx_tag` (`tag`),
  KEY `idx_key` (`key`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_channel_tag` (`channel`,`status`,`consumer_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息表';

CREATE TABLE `mq_message_log` (
  `ml_id` int(11) NOT NULL AUTO_INCREMENT,
  `channel` varchar(64) DEFAULT NULL,
  `tag` varchar(64) DEFAULT NULL,
  `key` varchar(128) DEFAULT NULL,
  `consumer_key` varchar(64) DEFAULT '',
  `body` longtext,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ml_id`),
  KEY `key` (`key`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息记录表';

CREATE TABLE `mq_tags` (
  `t_id` int(11) NOT NULL AUTO_INCREMENT,
  `channel` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道',
  `tag` varchar(64) NOT NULL DEFAULT '',
  `tag_type` tinyint(1) NOT NULL DEFAULT '2' COMMENT 'tag 类型，0-广播，1-订阅，2-直连',
  `note` varchar(100) NOT NULL DEFAULT '',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '创建日期',
  PRIMARY KEY (`t_id`),
  UNIQUE KEY `channel_tag` (`channel`,`tag`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='消息tag表';
