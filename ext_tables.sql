create table tx_ximatypo3calendar_domain_model_event (
	status smallint(5) unsigned default null
);

create table tx_ximatypo3calendar_domain_model_entry (
	type varchar(255) default 'inPerson' not null,
	modified_fields text,
	modified_date bigint
);

create table be_users (
	tx_ximatypo3calendar_notify_review smallint(5) unsigned default 0 not null,
	tx_ximatypo3calendar_notify_live smallint(5) unsigned default 0 not null,
	tx_ximatypo3calendar_notify_categories int(11) unsigned default 0 not null
);
