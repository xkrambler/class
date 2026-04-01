/*
	xImage: class for image threatment

	Usage example:
		(new xImage()).load({
			"url":"//inertinc.com/images/logo.png",
			"onload":function(xi, image){
				xi.scale({
					"width":256,
					"height":256,
					"limit":1,
					"onscale":function(xi, image){
						console.log(image);
						document.body.appendChild(image);
					}
				});
			}
		});

*/
function xImage(o) {
	var self=this;

	// image loader from file or URL
	self.load=function(o) {
		self.error("");
		if (o.file) self.file=o.file;
		if (o.url) self.url=o.url;

		var imageLoader=function(src){
			self.image=new Image();
			self.image.onerror=function(ev) {
				self.error("Failed to load image");
				self.image=null;
				if (o.onerror) o.onerror(self, ev);
			};
			self.image.onload=function() {
				self.width=self.image.width;
				self.height=self.image.height;
				if (o.onload) o.onload(self, self.image);
			};
			self.image.src=src;
		};

		if (self.file) {
			self.type=null;
			var reader=new FileReader();
			reader.onload=function(e){ imageLoader(e.target.result); };
			reader.readAsDataURL(self.file);
		} else if (self.url) {
			imageLoader(self.url);
		}

	};

	// image sharpener
	self.sharpener=function(o){
		var
			s=o.sharpen || self.sharpen,
			w=self.canvas.width,
			h=self.canvas.height,
			weights=[0, -1, 0, -1, 5, -1, 0, -1, 0],
			katet=Math.round(Math.sqrt(weights.length)),
			half=(katet*0.5)|0,
			ctx=self.ctx.createImageData(w, h),
			dst=ctx.data,
			src=self.ctx.getImageData(0, 0, w, h).data,
			y=h
		;
		while (y--) {
			var x=w;
			while (x--) {
				var sy=y, sx=x, offset=(y*w+x)*4, r=0, g=0, b=0, a=0;
				for (var cy=0; cy < katet; cy++) {
					for (var cx=0; cx < katet; cx++) {
						var scy=sy+cy-half;
						var scx=sx+cx-half;
						if (scy >= 0 && scy < h && scx >= 0 && scx < w) {
							var srcOff=(scy*w+scx)*4;
							var wt=weights[cy*katet+cx];
							r+=src[srcOff]*wt;
							g+=src[srcOff+1]*wt;
							b+=src[srcOff+2]*wt;
							a+=src[srcOff+3]*wt;
						}
					}
				}
				dst[offset]  =r*s+src[offset]  *(1-s);
				dst[offset+1]=g*s+src[offset+1]*(1-s);
				dst[offset+2]=b*s+src[offset+2]*(1-s);
				dst[offset+3]=src[offset+3];
			}
		}
		self.ctx.putImageData(ctx, 0, 0);
		return self.image;
	};

	// image scaler
	self.scale=function(o) {
		if (!self.image) return self.error("No image loaded.");
		if (!self.quality || self.quality <= 0 || self.quality > 1) return self.error("quality should be between 0 and 1.");
		if (!o.width || o.width <= 0) o.width=self.image.width;
		if (!o.height || o.height <= 0) o.height=self.image.height;
		if (o.quality > 0 && o.quality <= 1) self.quality=parseFloat(o.quality);
		if (typeof(o.limit) != "undefined") self.limit=parseFloat(o.limit);
		self.error("");

		self.started=new Date();

		var target_width=self.image.width;
		var target_height=self.image.height;

		// mantain proportions
		var scale_width=o.width/target_width;
		var scale_height=o.height/target_height;
		self.ratio=Math.min(scale_width, scale_height);
		if (self.limit) self.ratio=Math.min(self.ratio, self.limit);
		target_width=Math.round(target_width*self.ratio);
		target_height=Math.round(target_height*self.ratio);

		// set own properties
		self.width=target_width;
		self.height=target_height;

		// set canvas
		self.canvas=(
			(gid(self.preview) && gid(self.preview).getContext)
			?gid(self.preview)
			:document.createElement('canvas')
		);
		self.ctx=self.canvas.getContext('2d');

		// set target dimensions
		self.canvas.width=target_width;
		self.canvas.height=target_height;

		// if smoothing enabled
		if (self.smoothing) {

			// temporal canvas
			var tmp_canvas=document.createElement('canvas');
			var tmp_ctx=tmp_canvas.getContext('2d');

			tmp_canvas.width=self.image.width;
			tmp_canvas.height=self.image.height;

			tmp_ctx.imageSmoothingEnabled=true;
			tmp_ctx.imageSmoothingQuality=self.smoothing;
			tmp_ctx.drawImage(self.image, 0, 0);

			var current_width=self.image.width;
			var current_height=self.image.height;

			// progressive scaling
			while (current_width*0.5 > target_width) {
				current_width*=0.5;
				current_height*=0.5;

				var step_canvas=document.createElement('canvas');
				var step_ctx=step_canvas.getContext('2d');

				step_canvas.width=current_width;
				step_canvas.height=current_height;

				step_ctx.imageSmoothingEnabled=true;
				step_ctx.imageSmoothingQuality=self.smoothing;

				step_ctx.drawImage(tmp_canvas, 0, 0, current_width, current_height);

				tmp_canvas=step_canvas;
			}

			// render
			self.ctx.imageSmoothingEnabled=true;
			self.ctx.imageSmoothingQuality=self.smoothing;
			self.ctx.drawImage(tmp_canvas, 0, 0, target_width, target_height);

		// fast mode
		} else {

			// render
			self.ctx.drawImage(self.image, 0, 0, target_width, target_height);

		}

		// sharpenning if requested
		var sharpen=(o.sharpen || self.sharpen);
		if (sharpen) self.sharpener({"sharpen":sharpen});

		// on scale event
		self.time=((new Date()).getTime()-self.started.getTime())/1000;
		if (o.onscale) o.onscale(self, self.canvas);

		// on blob event
		if (o.onblob) self.toBlob({"onblob":o.onblob});

		// fine
		return true;

	};

	// convert to blob
	self.toBlob=function(o){
		if (!self.canvas) return self.error("canvas still not generated.");
		var o=o||{};
		if (o.type) self.type=o.type;
		if (!self.started) self.started=new Date();
		self.blob=null;
		if (o.onblob) self.canvas.toBlob(function(blob) {
			self.blob=blob;
			self.time=((new Date()).getTime()-self.started.getTime())/1000;
			o.onblob(self, blob);
			self.started=null;
		}, (o.type?o.type:self.type) || (self.file?self.file.type:'image/jpeg'), self.quality);
	};

	// get/set error
	self.error=function(error){
		if (typeof(error) != "undefined") {
			self.err=error;
			return false;
		}
		return self.err;
	};

	// init
	self.init=function(o){

		self.smoothing="high";
		self.type=null;
		self.url=null;
		self.file=null;
		self.image=null;
		self.canvas=null;
		self.ctx=null;
		self.blob=null;
		self.quality=0.85;
		self.ratio=null;
		self.limit=1;
		self.width=0;
		self.height=0;
		self.err="";

		for (var k in o) self[k]=o[k];

	};

	self.init(o);

}
