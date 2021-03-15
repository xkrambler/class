var xclipboard={

	// copy as a kind of content
	"copyAs":function(html, mimetypes) {
		function listener(e) {
			for (var i in mimetypes)
				e.clipboardData.setData(mimetypes[i], html);
			e.preventDefault();
		}
		document.addEventListener("copy", listener);
		document.execCommand("copy");
		document.removeEventListener("copy", listener);
		return true;
	},

	// copy as text (generic method, more support)
	"copyText":function(text) {
		var input=document.createElement("input");
		input.type="text";
		input.value=text;
		document.body.appendChild(input);
		input.select();
		input.focus();
		document.execCommand("copy");
		input.parentNode.removeChild(input);
		return true;
	},

	// copy content as HTML
	"copyHTML":function(html) {
		return xclipboard.copyAs(html, ["text/html", "text/plain"]);
	}

};
