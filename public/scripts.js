function show_if_test (condition) {
  var target_id = "i_" + condition[0];
  var elts = $(document.getElementsByName (target_id))
  if (elts.length == 0)
    return true;

  switch ($(elts[0]).attr("type")) {
  case "radio":
    var val = $(elts)
      .filter ("input:checked")
      .val();
    return (condition.includes (val));
  case "text":
    return ($(elts).val() != "");
  default:
    return false;
  }
}

function update_sched_hides () {
  let val = $("input[name=i_app_category]:checked").val();
  if (val == "Performance") {
    $(".sched_ext").hide();
  } else {
    $(".sched_ext").show();
  }
}

function update_hides () {
  update_sched_hides ();

  let show_all = $("#show_all").is(":checked");

  for (var idx in questions) {
    var q = questions[idx];
    var want_field = true;

    if (q.show_if) {
      if (Array.isArray (q.show_if[0])) {
	q.show_if.forEach (function (condition) {
	  if (! show_if_test (condition))
	    want_field = false;
	});
      } else {
	if (! show_if_test (q.show_if))
	  want_field = false;
      }
    }

    if (show_all)
      want_field = true;
    
    var section_id = "s_" + q.id;
    var elt = document.getElementById ("s_"+q.id);
    if (want_field) {
      $(elt).show();
    } else {
      $(elt).hide();
    }
  }

  if (show_all)
    $(".debug").show();
  else
    $(".debug").hide();
}

function is_radio_complete (q) {
  let saw_blank = false;
  let section_id = "s_"+q.id;
  $(document.getElementById(section_id))
    .find("input[type=radio]")
    .each(function (idx, elt) {
      if (! $(elt).is(":hidden")) {
	let name = $(elt).attr("name");
	let val = $(document.getElementsByName (name))
	    .filter ("input:checked")
	    .val();
	if (val == undefined)
	  saw_blank = true;
      }
    });
  return (! saw_blank);
}

function is_choice_complete (q) {
  let input_id = "i_"+q.id;
  val = $(document.getElementsByName (input_id))
    .filter("input:checked")
    .val();
  if (val != undefined && val.trim() != "")
    return (true);

  return (false);
}

function is_email_valid (q) {
  let input_id = "i_"+q.id;
  let email = $(document.getElementById (input_id)).val();
  return /\S+@\S+\.\S+/.test(email);
}

function valid_response (q) {
  if ($("#all_optional").is(":checked"))
    return (true);

  if (q.show_if) {
    var elt = document.getElementById ("s_"+q.id);
    if ($(elt).is(":hidden"))
      return (true);
  }

  if (q.optional)
    return (true);

  if (q.type == "email")
    return (is_email_valid (q));

  if (q.type == "radio")
    return (is_radio_complete (q));

  if (q.choices)
    return (is_choice_complete (q));

  let input_id = "i_"+q.id;
  let val;
  val = $(document.getElementById (input_id)).val();

  if (val != undefined && val.trim() != "")
    return (true);

  return (false);
}

function apply_submit () {
  for (var idx in questions) {
    var q = questions[idx];
    if (! valid_response (q)) {
      var section = document.getElementById("s_"+q.id);
      $(section).find(".required_text").html("required");
      $(window).scrollTop ($(section).offset().top);
      return (false); /* kill submit */
    }
  }
  return (true); /* ok for submit to go through */
}

function do_comma (elt) {
  let val = $(elt).val();
  if ($(elt).hasClass ("lookup_individual")) {
    if (! val.match(/,/)) {
      let matches = val.trim().match (/^([\S]*)\s(.*)$/);
      if (matches) {
	let first = matches[1];
	let last = matches[2];
	$(elt).val(last.trim() + "," + first.trim());
      }
    }
  } else if ($(elt).hasClass ("lookup_group")) {
    if (! val.match(/,/)) {
      let matches = val.trim().match (/^The\s(.*)/i);
      if (matches) {
	let name = matches[1];
	val = name.trim() + ",The";
      }
      val = val.replace (/\band\b/gi, "&");
      $(elt).val(val);
    }
  }
}

function do_lookup_change (ev) {
  let input_elt = $(ev.target);
  let val = $(input_elt).val().trim();

  let input_wrapper = $(input_elt).parents(".input_wrapper");
  let span = $(input_elt).parents("span");

  if (val == "") {
    $(input_wrapper).find(".group_members").remove();
    $(span).find(".lookup_success_msg").remove();
    $(span).find(".lookup_fail_msg").remove();
  } else {
    $.getJSON ("lookup_check.php",
	       { name: val },
	       (ret) => {
		 $(input_wrapper).find(".group_members").remove();
		 $(span).find(".lookup_success_msg").remove();
		 $(span).find(".lookup_fail_msg").remove();
		 $(span).find(".initial_attention").remove();
		 if (typeof (ret) != "object")
		   ret = {};
		 let txt = "";
		 if (ret.id) {
		   txt += "<span class='lookup_success_msg'>";
		   txt += "Good match in master database!";
		   txt += "</span>";
		 } else {
		   txt += "<div class='lookup_fail_msg'>";
		   txt += "We'll create this new name in master database"
		     +" (but if you think the name should exist,"
		     +" visit <a target='_blank'"
		     +" href='https://cgi.neffa.org//public/showperf.pl?INDEX=ALL'>"
		     +" the NEFFA Database</a> to do some hunting).";
		   txt += "</div>";

		   do_comma (input_elt);
		 }
		 $(span).append (txt);
		 
		 if (ret.group && ret.members) {
		   $(input_wrapper).remove (".group_members");
		   var div = document.createElement("div");
		   $(div).attr ("class", "group_members");
		   div.textContent = "Current members: "
		     + ret.members.join ("; ");
		   $(input_wrapper)[0].appendChild (div);
		 }
	       });
  }
  return (true);
}

function setup_lookups () {
  $(".lookup_individual").autocomplete({ source: "lookup_individual.php" });
  $(".lookup_individual").attr("autocomplete","correspondent-name");
  $(".lookup_individual").off ("change.neffa");
  $(".lookup_individual").on ("change.neffa", do_lookup_change);

  $(".lookup_group").autocomplete({ source: "lookup_group.php" });
  $(".lookup_group").attr("autocomplete","correspondent-name");
  $(".lookup_group").off ("change.neffa");
  $(".lookup_group").on ("change.neffa", do_lookup_change);
}

function do_sched_any (ev) {
  let elt = ev.target;
  let checked = elt.checked;

  if (checked) {
    $("#s_availability input[type=radio]").each (function (idx, elt) {
      if (! $(elt).is(":hidden")) {
	if ($(elt).val() == 1)
	  $(elt).prop ("checked", true);
      }
    });

    $(".sched_all_day").prop ("checked", true);
    $(".sched_not_day").prop ("checked", false);
  }
}

function do_all_day (ev) {
  var elt = ev.target;
  var checked = elt.checked;
  var day = $(elt).data("day");

  if (checked) {
    $("#s_availability input[type=radio]").each (function (idx, elt) {
      if (! $(elt).is(":hidden")) {
	if ($(elt).data("day") == day && $(elt).val() == 1) {
	  $(elt).prop ("checked", true);
	}
      }
    });
  }

  $("#sched_any").prop ("checked", false);
  $(".sched_not_day").prop ("checked", false);
}

function do_not_day (ev) {
  var elt = ev.target;
  var checked = elt.checked;
  var day = $(elt).data("day");

  if (checked) {
    $("#s_availability input[type=radio]").each (function (idx, elt) {
      if (! $(elt).is(":hidden")) {
	if ($(elt).data("day") == day && $(elt).val() == 0) {
	  $(elt).prop ("checked", true);
	}
      }
    });
  }

  $("#sched_any").prop ("checked", false);
  $(".sched_all_day").prop ("checked", false);
}

function do_sched_item (ev) {
  $("#sched_any").prop ("checked", false);
  $(".sched_all_day").prop ("checked", false);
  $(".sched_not_day").prop ("checked", false);
}

function do_session_option (ev) {
  var elt = ev.target;
  var val = elt.checked ? 1 : 0;
  $.get ("set_session_option.php", {
    "var": elt.id,
    "val": val
  });
}

$(function () {
  $("input").change (update_hides);
  $("#apply_form").submit (apply_submit);

  $("#sched_any").change (do_sched_any);
  $(".sched_all_day").change (do_all_day);
  $(".sched_not_day").change (do_not_day);
  $(".sched_item").change (do_sched_item);

  if (window.questions)
    update_hides ();

  setup_lookups ();
  
  $("#show_all").change (do_session_option);
  $("#all_optional").change (do_session_option);

});
