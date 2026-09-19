import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	SelectControl,
	RangeControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the Time Machine block.
 *
 * The block is rendered server side (see classes/class-block.php), so the
 * editor only needs to expose the settings and preview the real output
 * through ServerSideRender - there is no separate rendering logic to keep
 * in sync here.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Current block attributes.
 * @param {Function} props.setAttributes Update block attributes.
 *
 * @return {JSX.Element} Editor markup.
 */
export default function Edit( { attributes, setAttributes } ) {

	const {
		title,
		message,
		posts,
		showifno,
		private: includePrivate,
		excludePages,
		excludeCurrent,
		displayCommentNum,
		range,
		offset,
		direction,
		excerpt,
		excerptCut,
		excerptLength,
	} = attributes;

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Content', 'time-machine' ) }>
					<TextControl
						label={ __( 'Title', 'time-machine' ) }
						value={ title }
						placeholder={ __( 'Time Machine', 'time-machine' ) }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
					<TextControl
						label={ __( 'Message if no articles in past', 'time-machine' ) }
						value={ message }
						placeholder={ __( 'No articles published on same day in past', 'time-machine' ) }
						onChange={ ( value ) => setAttributes( { message: value } ) }
					/>
					<RangeControl
						label={ __( 'Number of articles', 'time-machine' ) }
						value={ posts }
						onChange={ ( value ) => setAttributes( { posts: value } ) }
						min={ 1 }
						max={ 50 }
					/>
					<ToggleControl
						label={ __( 'Show block even if no articles in past', 'time-machine' ) }
						checked={ showifno }
						onChange={ ( value ) => setAttributes( { showifno: value } ) }
					/>
					<ToggleControl
						label={ __( 'Include password protected articles', 'time-machine' ) }
						checked={ includePrivate }
						onChange={ ( value ) => setAttributes( { private: value } ) }
					/>
					<ToggleControl
						label={ __( 'Exclude pages', 'time-machine' ) }
						checked={ excludePages }
						onChange={ ( value ) => setAttributes( { excludePages: value } ) }
					/>
					<ToggleControl
						label={ __( 'Exclude articles from this year', 'time-machine' ) }
						checked={ excludeCurrent }
						onChange={ ( value ) => setAttributes( { excludeCurrent: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show number of comments', 'time-machine' ) }
						checked={ displayCommentNum }
						onChange={ ( value ) => setAttributes( { displayCommentNum: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Time range', 'time-machine' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Range', 'time-machine' ) }
						value={ range }
						options={ [
							{ label: __( 'Disable offset', 'time-machine' ), value: 'none' },
							{ label: __( 'Days', 'time-machine' ), value: 'days' },
							{ label: __( 'Weeks', 'time-machine' ), value: 'weeks' },
							{ label: __( 'Months', 'time-machine' ), value: 'months' },
						] }
						onChange={ ( value ) => setAttributes( { range: value } ) }
					/>
					<RangeControl
						label={ __( 'Offset', 'time-machine' ) }
						value={ offset }
						onChange={ ( value ) => setAttributes( { offset: value } ) }
						min={ 1 }
						max={ 100 }
						disabled={ 'none' === range }
					/>
					<SelectControl
						label={ __( 'Direction', 'time-machine' ) }
						value={ direction }
						options={ [
							{ label: __( 'Before [-]', 'time-machine' ), value: 'before' },
							{ label: __( 'Both [+/-]', 'time-machine' ), value: 'both' },
							{ label: __( 'After [+]', 'time-machine' ), value: 'after' },
						] }
						onChange={ ( value ) => setAttributes( { direction: value } ) }
						disabled={ 'none' === range }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Article excerpt', 'time-machine' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show excerpt', 'time-machine' ) }
						checked={ excerpt }
						onChange={ ( value ) => setAttributes( { excerpt: value } ) }
					/>
					<ToggleControl
						label={ __( 'Shorten excerpt', 'time-machine' ) }
						checked={ excerptCut }
						onChange={ ( value ) => setAttributes( { excerptCut: value } ) }
						disabled={ ! excerpt }
					/>
					<RangeControl
						label={ __( 'Excerpt length (words)', 'time-machine' ) }
						value={ excerptLength }
						onChange={ ( value ) => setAttributes( { excerptLength: value } ) }
						min={ 5 }
						max={ 100 }
						disabled={ ! excerpt || ! excerptCut }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="time-machine/time-machine"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
