import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { feedId, layout, count } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Feed settings', 'thewpfeeds' ) }>
					<FeedSelect
						value={ feedId }
						onChange={ ( value ) =>
							setAttributes( { feedId: parseInt( value, 10 ) || 0 } )
						}
					/>
					<SelectControl
						label={ __( 'Layout', 'thewpfeeds' ) }
						value={ layout }
						options={ [
							{ label: __( 'Feed default', 'thewpfeeds' ), value: '' },
							{ label: __( 'Grid', 'thewpfeeds' ), value: 'grid' },
							{ label: __( 'List', 'thewpfeeds' ), value: 'list' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
						help={ __(
							'Themes can add custom layouts via thewpfeeds/layout-{name}.php.',
							'thewpfeeds'
						) }
					/>
					<RangeControl
						label={ __( 'Items (0 = feed default)', 'thewpfeeds' ) }
						value={ count }
						min={ 0 }
						max={ 50 }
						onChange={ ( value ) => setAttributes( { count: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ feedId === 0 ? (
				<Placeholder
					icon="rss"
					label={ __( 'The WP Feeds', 'thewpfeeds' ) }
					instructions={ __(
						'Select a feed in the block settings sidebar. Feeds are managed under the “Feeds” admin menu.',
						'thewpfeeds'
					) }
				/>
			) : (
				<ServerSideRender
					block="thewpfeeds/feed"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => <Spinner /> }
				/>
			) }
		</div>
	);
}

function FeedSelect( { value, onChange } ) {
	const [ feeds, setFeeds ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/thewpfeeds/v1/feeds' } )
			.then( setFeeds )
			.catch( () => setFeeds( [] ) );
	}, [] );

	if ( feeds === null ) {
		return <Spinner />;
	}

	return (
		<SelectControl
			label={ __( 'Feed', 'thewpfeeds' ) }
			value={ String( value ) }
			options={ [
				{ label: __( '— select a feed —', 'thewpfeeds' ), value: '0' },
				...feeds.map( ( feed ) => ( {
					label: `${ feed.name } (${ feed.slug })`,
					value: String( feed.id ),
				} ) ),
			] }
			onChange={ onChange }
		/>
	);
}
