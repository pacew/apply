var foo;

function update_hides () {
  for (var idx in questions) {
    var q = questions[idx];
    if (q.show_if) {
      var section_id = "s_" + q.id;
      var target_id = "i_" + q.show_if[0];
      var val = $("input[name='"+target_id+"']:checked").val();
      var match = false;
      if (q.show_if.includes (val)) {
	$("#"+section_id).show();
      } else {
	$("#"+section_id).hide();
      }
    }
  }
}

$(function () {
  update_hides ();

  $("input[type='radio']").change (update_hides);
});
