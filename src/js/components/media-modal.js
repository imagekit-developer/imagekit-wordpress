if (wp.media) {

	function generateImageKitSearchQuery(type) {

		if (typeof type !== 'string' && !Array.isArray(type) && !(Array.isArray(type) && type.length === 0)) {
			return;
		}

		const TYPE_TO_EXTENSIONS = {
			image: [
				"jpg", "jpeg", "png", "webp", "gif", "svg", "avif", "bmp", "tiff", "ico", "heic", "heif"
			],
			video: [
				"mp4", "webm", "mov", "avi", "mkv", "flv", "wmv", "ts", "m3u8", "3gp", "swf"
			],
			audio: [
				"mp3", "wav", "ogg", "m4a", "aac", "flac", "opus"
			],
			application: [
				"pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "zip", "rar", "7z", "js", "css", "json", "xml", "woff", "woff2", "ttf", "otf", "eot"
			],
			text: [
				"txt", "csv", "md", "log", "yaml", "yml"
			]
		};

		const extensions = []

		if (Array.isArray(type)) {
			for (const t of type) {
				if (TYPE_TO_EXTENSIONS[t]) {
					extensions.push(...TYPE_TO_EXTENSIONS[t])
				}
			}

		} else {
			if (TYPE_TO_EXTENSIONS[type]) {
				extensions.push(...TYPE_TO_EXTENSIONS[type])
			}
		}

		if (extensions.length === 0) {
			return;
		}

		return `(format IN [${extensions.map(ext => `"${ext}"`).join(',')}])`;
	}


	const MediaFrame = wp.media.view.MediaFrame.Select;
	const MediaFramePost = wp.media.view.MediaFrame.Post;
	const MediaFrameImageDetails = wp.media.view.MediaFrame.ImageDetails;
	const MediaFrameVideoDetails = wp.media.view.MediaFrame.VideoDetails;
	const AttachmentDisplay = wp.media.view.Settings.AttachmentDisplay;
	const ImageKit = wp.media.View.extend({
		tagName: 'div',
		className: 'ik-eml-widget',
		id: "ik-eml-widget",
		template: wp.template('imagekit-eml'),
		active: false,
		toolbar: null,
		frame: null,
		ready() {
			const controller = this.controller;
			const selection = this.model.get('selection');
			const library = this.model.get('library');
			const attachment = wp.media.model.Attachment;

			const imagekitMediaLibraryWidgetOptions = IKML.widgetConfig

			imagekitMediaLibraryWidgetOptions.mlSettings = imagekitMediaLibraryWidgetOptions.mlSettings ?? {};

			imagekitMediaLibraryWidgetOptions.mlSettings.multiple = controller.options.multiple

			try {
				const type = selection.props.attributes.type;

				let searchQuery = generateImageKitSearchQuery(type);

				imagekitMediaLibraryWidgetOptions.mlSettings.initialView = imagekitMediaLibraryWidgetOptions.mlSettings.initialView ?? {};
				if (searchQuery) {
					imagekitMediaLibraryWidgetOptions.mlSettings.initialView.searchQuery = searchQuery;
				}
			} catch (err) { }

			if (this.cid !== this.active) {
				console.log(imagekitMediaLibraryWidgetOptions)
				const widget = new IKMediaLibraryWidget(imagekitMediaLibraryWidgetOptions, ({ eventType, data }) => {
					if (eventType === 'INSERT') {
						for (let i = 0; i < data.length; i++) {
							const asset = data[i];
							wp.media.post('imagekit-down-sync', {
								nonce: IKML.nonce,
								asset,
							}).done((asset) => {

							})
						}
					}
				})

			}
			this.active = this.cid;
			return this;
		},
		remove() {
			console.log('view is being destroyed');
		}
	})

	const extendType = function (type) {
		const obj = {
			/**
			 * Bind region mode event callbacks.
			 *
			 * @see media.controller.Region.render
			 */
			bindHandlers() {
				type.prototype.bindHandlers.apply(this, arguments);
				this.on(
					'content:render:imagekit',
					this.imagekitContent,
					this
				);
			},
			/**
			 * Render callback for the router region in the `browse` mode.
			 *
			 * @param {wp.media.view.Router} routerView
			 */
			browseRouter(routerView) {
				type.prototype.browseRouter.apply(this, arguments);
				routerView.set({
					imagekit: {
						text: 'ImageKit',
						priority: 60,
					},
				});
			},

			/**
			 * Render callback for the content region in the `upload` mode.
			 */
			imagekitContent() {
				this.$el.addClass('hide-toolbar');
				const state = this.state();
				const view = new ImageKit({
					controller: this,
					model: state,
				}).render();
				this.content.set(view);
			},
		};

		return obj;
	};

	const extendDetails = function (type) {
		return {
			initialize() {
				type.prototype.initialize.apply(this, arguments);
				this.listenTo(
					this.model,
					'change:cldoverwrite',
					this.handleOverwrite
				);
			},
			handleOverwrite(data) {
				const sizes = this.options.attachment.attributes.sizes;
				for (const size in sizes) {
					const url = new URL(sizes[size].url);
					if (data.attributes.cldoverwrite) {
						url.searchParams.set('cld_overwrite', true);
					} else {
						url.searchParams.delete('cld_overwrite');
					}
					this.options.attachment.attributes.sizes[size].url =
						url.href;
				}
			},
		};
	};

	wp.media.view.MediaFrame.Select = MediaFrame.extend(
		extendType(MediaFrame)
	);
	wp.media.view.MediaFrame.Post = MediaFramePost.extend(
		extendType(MediaFramePost)
	);
	wp.media.view.MediaFrame.ImageDetails = MediaFrameImageDetails.extend(
		extendType(MediaFrameImageDetails)
	);
	wp.media.view.MediaFrame.VideoDetails = MediaFrameVideoDetails.extend(
		extendType(MediaFrameVideoDetails)
	);
	wp.media.view.Settings.AttachmentDisplay = AttachmentDisplay.extend(
		extendDetails(AttachmentDisplay)
	);
}