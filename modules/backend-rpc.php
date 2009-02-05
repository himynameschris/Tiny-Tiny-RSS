<?php
	function handle_rpc_request($link) {

		$subop = $_GET["subop"];

		if ($subop == "setpref") {
			if (WEB_DEMO_MODE) {
				return;
			}

			print "<rpc-reply>";

			$key = db_escape_string($_GET["key"]);
			$value = db_escape_string($_GET["value"]);

			set_pref($link, $key, $value);

			print "<param-set key=\"$key\" value=\"$value\"/>";

			print "</rpc-reply>";

			return;
		}

		if ($subop == "getLabelCounters") {
			$aid = $_GET["aid"];		
			print "<rpc-reply>";
			print "<counters>";
			getLabelCounters($link);
			if ($aid) {
				getFeedCounter($link, $aid);
			}
			print "</counters>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "getFeedCounters") {
			print "<rpc-reply>";
			print "<counters>";
			getFeedCounters($link);
			print "</counters>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "getAllCounters") {
			print "<rpc-reply>";			
			print "<counters>";

			$omode = $_GET["omode"];

			getAllCounters($link, $omode);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "mark") {
			$mark = $_GET["mark"];
			$id = db_escape_string($_GET["id"]);

			if ($mark == "1") {
				$mark = "true";
			} else {
				$mark = "false";
			}

			// FIXME this needs collision testing

			$result = db_query($link, "UPDATE ttrss_user_entries SET marked = $mark
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

			print "<rpc-reply><counters>";
			getGlobalCounters($link);
			getLabelCounters($link);
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
			print "</counters></rpc-reply>";

			return;
		}

		if ($subop == "publ") {
			$pub = $_GET["pub"];
			$id = db_escape_string($_GET["id"]);

			if ($pub == "1") {
				$pub = "true";
			} else {
				$pub = "false";
			}

			// FIXME this needs collision testing

			$result = db_query($link, "UPDATE ttrss_user_entries SET published = $pub
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

			print "<rpc-reply><counters>";
			getGlobalCounters($link);
			getLabelCounters($link);
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
			print "</counters></rpc-reply>";

			return;
		}

		if ($subop == "updateFeed") {
			$feed_id = db_escape_string($_GET["feed"]);

			$result = db_query($link, 
				"SELECT feed_url FROM ttrss_feeds WHERE id = '$feed_id'
					AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) > 0) {			
				$feed_url = db_fetch_result($result, 0, "feed_url");
				update_rss_feed($link, $feed_url, $feed_id);
			}

			print "<rpc-reply>";	
			print "<counters>";
			getFeedCounter($link, $feed_id);
			print "</counters>";
			print "</rpc-reply>";
			
			return;
		}

		if ($subop == "forceUpdateAllFeeds" || $subop == "updateAllFeeds") {
	
			$global_unread_caller = sprintf("%d", $_GET["uctr"]);
			$global_unread = getGlobalUnread($link);

			print "<rpc-reply>";

			print "<counters>";

			if ($global_unread_caller != $global_unread) {

	 			$omode = $_GET["omode"];
	 
	 			if (!$omode) $omode = "tflc";

	 			if (strchr($omode, "l")) getLabelCounters($link);

	 			if (strchr($omode, "c")) {			
		 			if (get_pref($link, 'ENABLE_FEED_CATS')) {
		 				getCategoryCounters($link);
		 			}
				}

	 			if (strchr($omode, "f")) getFeedCounters($link);
				if (strchr($omode, "t")) getTagCounters($link);

				getGlobalCounters($link, $global_unread);
			}
 
			print "</counters>";

			print_runtime_info($link);

			print "</rpc-reply>";

			return;
		}

		/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
		if ($subop == "catchupSelected") {

			$ids = split(",", db_escape_string($_GET["ids"]));
			$cmode = sprintf("%d", $_GET["cmode"]);

			catchupArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link, $_GET["omode"]);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "markSelected") {

			$ids = split(",", db_escape_string($_GET["ids"]));
			$cmode = sprintf("%d", $_GET["cmode"]);

			markArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link, $_GET["omode"]);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "publishSelected") {

			$ids = split(",", db_escape_string($_GET["ids"]));
			$cmode = sprintf("%d", $_GET["cmode"]);

			publishArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link, $_GET["omode"]);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "sanityCheck") {
			print "<rpc-reply>";
			if (sanity_check($link)) {
				print "<error error-code=\"0\"/>";
				print_init_params($link);
				print_runtime_info($link);

				# assign client-passed params to session
				$_SESSION["client.userAgent"] = $_GET["ua"];

			}
			print "</rpc-reply>";

			return;
		}		

		if ($subop == "globalPurge") {

			print "<rpc-reply>";
			global_purge_old_posts($link, true);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "getArticleLink") {

			$id = db_escape_string($_GET["id"]);

			$result = db_query($link, "SELECT link FROM ttrss_entries, ttrss_user_entries
				WHERE id = '$id' AND id = ref_id AND owner_uid = '".$_SESSION['uid']."'");

			if (db_num_rows($result) == 1) {
				$link = htmlspecialchars(strip_tags(db_fetch_result($result, 0, "link")));
				print "<rpc-reply><link>$link</link><id>$id</id></rpc-reply>";
			} else {
				print "<rpc-reply><error>Article not found</error></rpc-reply>";
			}

			return;
		}

		if ($subop == "setArticleTags") {

			$id = db_escape_string($_GET["id"]);

			$tags_str = db_escape_string($_GET["tags_str"]);

			$tags = array_unique(trim_array(split(",", $tags_str)));

			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$id' AND owner_uid = '".$_SESSION["uid"]."' LIMIT 1");

			if (db_num_rows($result) == 1) {

				$int_id = db_fetch_result($result, 0, "int_id");

				db_query($link, "DELETE FROM ttrss_tags WHERE 
					post_int_id = $int_id AND owner_uid = '".$_SESSION["uid"]."'");

				foreach ($tags as $tag) {
					$tag = sanitize_tag($tag);	

					if (!tag_is_valid($tag)) {
						continue;
					}

					if (preg_match("/^[0-9]*$/", $tag)) {
						continue;
					}

//					print "<!-- $id : $int_id : $tag -->";
					
					if ($tag != '') {
						db_query($link, "INSERT INTO ttrss_tags 
							(post_int_id, owner_uid, tag_name) VALUES ('$int_id', '".$_SESSION["uid"]."', '$tag')");
					}
				}
			}

			db_query($link, "COMMIT");

			$tags_str = format_tags_string(get_article_tags($link, $id), $id);

			print "<rpc-reply>
				<tags-str id=\"$id\"><![CDATA[$tags_str]]></tags-str>
				</rpc-reply>";

			return;
		}

		if ($subop == "regenPubKey") {

			print "<rpc-reply>";

			set_pref($link, "_PREFS_PUBLISH_KEY", generate_publish_key());

			$new_link = article_publish_url($link);		

			print "<link><![CDATA[$new_link]]></link>";

			print "</rpc-reply>";

			return;
		}

		if ($subop == "logout") {
			logout_user();
			print_error_xml(6);
			return;
		}

		if ($subop == "completeTags") {

			$search = db_escape_string($_REQUEST["search"]);

			$result = db_query($link, "SELECT DISTINCT tag_name FROM ttrss_tags 
				WHERE owner_uid = '".$_SESSION["uid"]."' AND
			  	tag_name LIKE '$search%' ORDER BY tag_name
				LIMIT 10");

			print "<ul>";
			while ($line = db_fetch_assoc($result)) {
				print "<li>" . $line["tag_name"] . "</li>";
			}
			print "</ul>";

			return;
		}

		if ($subop == "purge") {
			$ids = split(",", db_escape_string($_GET["ids"]));
			$days = sprintf("%d", $_GET["days"]);

			print "<rpc-reply>";

			print "<message><![CDATA[";

			foreach ($ids as $id) {

				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
					id = '$id' AND owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) == 1) {
					purge_feed($link, $id, $days, true);
				}
			}

			print "]]></message>";

			print "</rpc-reply>";

			return;
		}

/*		if ($subop == "setScore") {
			$id = db_escape_string($_REQUEST["id"]);
			$score = sprintf("%d", $_REQUEST["score"]);

			$result = db_query($link, "UPDATE ttrss_user_entries SET score = '$score'
				WHERE ref_id = '$id' AND owner_uid = ".$_SESSION["uid"]);

			print "<rpc-reply><message>Acknowledged.</message></rpc-reply>";

			return;

		} */

		if ($subop == "getArticles") {
			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			print "<rpc-reply>";

			foreach ($ids as $id) {
				if ($id) {
					outputArticleXML($link, $id, 0, false);
				}
			}
			print "</rpc-reply>";

			return;
		}

		if ($subop == "checkDate") {

			$date = db_escape_string($_REQUEST["date"]);
			$date_parsed = strtotime($date);

			print "<rpc-reply>";

			if ($date_parsed) {
				print "<result>1</result>";
			} else {
				print "<result>0</result>";
			}

			print "</rpc-reply>";

			return;
		}

		if ($subop == "removeFromLabel") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$label_id = db_escape_string($_REQUEST["lid"]);

			$label = label_find_caption($link, $label_id, $_SESSION["uid"]);

			print "<rpc-reply>";
			print "<info-for-headlines>";

			if ($label) {

				foreach ($ids as $id) {
					label_remove_article($link, $id, $label, $_SESSION["uid"]);

					print "<entry id=\"$id\"><![CDATA[";

					$labels = get_article_labels($link, $id, $_SESSION["uid"]);
					print format_article_labels($labels, $id);

					print "]]></entry>";

				}
			}

			print "</info-for-headlines>";

			print "<counters>";
			getAllCounters($link, $omode);
			print "</counters>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "assignToLabel") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$label_id = db_escape_string($_REQUEST["lid"]);

			$label = label_find_caption($link, $label_id, $_SESSION["uid"]);

			print "<rpc-reply>";			

			print "<info-for-headlines>";

			if ($label) {

				foreach ($ids as $id) {
					label_add_article($link, $id, $label, $_SESSION["uid"]);

					print "<entry id=\"$id\"><![CDATA[";

					$labels = get_article_labels($link, $id, $_SESSION["uid"]);
					print format_article_labels($labels, $id);

					print "]]></entry>";

				}
			}

			print "</info-for-headlines>";

			print "<counters>";
			getAllCounters($link, $omode);
			print "</counters>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "feedBrowser") {

			$search = db_escape_string($_REQUEST["search"]);
			$limit = db_escape_string($_REQUEST["limit"]);

			print "<rpc-reply>";
			print "<content>";
			print "<![CDATA[";
			$ctr = print_feed_browser($link, $search, $limit);
			print "]]>";
			print "</content>";
			print "<num-results value=\"$ctr\"/>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "download") {
			$stage = (int) $_REQUEST["stage"];
			$cidt = db_escape_string($_REQUEST["cidt"]);
			$cidb = db_escape_string($_REQUEST["cidb"]);
			//$amount = (int) $_REQUEST["amount"];
			//$unread_only = db_escape_string($_REQUEST["unread_only"]);
			//if (!$amount) $amount = 50;

			$amount = 100;
			$unread_only = true;

			print "<rpc-reply>";

			if ($stage == 0) {
				print "<feeds>";

				$reply = array();

				$result = db_query($link, "SELECT id, title FROM
					ttrss_feeds WHERE owner_uid = ".$_SESSION["uid"]);

				while ($line = db_fetch_assoc($result)) {

					$has_icon = (int) feed_has_icon($line["id"]);

					print "<feed has_icon=\"$has_icon\" id=\"".$line["id"]."\"><![CDATA[";
					print $line["title"];
					print "]]></feed>";
				}

				print "</feeds>";

			}

			if ($stage > 0) {

				print "<articles>";

				$limit = 50;
				$skip = $limit*($stage-1);

				print "<limit value=\"$limit\"/>";

				if ($amount > 0) $amount -= $skip;

				if ($amount > 0) {

					$limit = min($limit, $amount);

					if ($unread_only) {
						$unread_qpart = "(unread = true OR marked = true) AND ";
					}

					if ($cidt && $cidb) {
						$cid_qpart =  "(id > $cidt OR id < $cidb) AND ";
					}

					if (DB_TYPE == "pgsql") {
						$date_qpart = "updated >= NOW() - INTERVAL '1 month' AND";
					} else {
						$date_qpart = "updated >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND";
					}			

					$result = db_query($link,
						"SELECT DISTINCT id,title,guid,link,comments,
								feed_id,content,updated,unread,marked FROM
							ttrss_user_entries,ttrss_entries
							WHERE $unread_qpart $cid_qpart $date_qpart
							ref_id = id AND owner_uid = ".$_SESSION["uid"]."
							ORDER BY updated DESC LIMIT $limit OFFSET $skip");

					if (function_exists('json_encode')) {

						while ($line = db_fetch_assoc($result)) {
							print "<article><![CDATA[";
	
							$line["marked"] = (int)sql_bool_to_bool($line["marked"]);
							$line["unread"] = (int)sql_bool_to_bool($line["unread"]);

//							too slow :(							
//							$line["tags"] = format_tags_string(
//								get_article_tags($link, $line["id"]), $line["id"]);
	
							print json_encode($line);
							print "]]></article>";
						}	
					}

				}

				print "</articles>";

			}

			print "</rpc-reply>";

			return;
		}

		print "<rpc-reply><error>Unknown method: $subop</error></rpc-reply>";
	}
?>
