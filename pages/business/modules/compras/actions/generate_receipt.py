#!/usr/bin/env python3
"""
================================================================================
VISIONGREEN - GERADOR DE RECIBOS PDF
Arquivo: pages/business/modules/compras/actions/generate_receipt.py
Descrição: Gera PDF profissional do recibo com ReportLab
================================================================================
"""

import sys
import json
from datetime import datetime
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, Image
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_RIGHT, TA_LEFT
from reportlab.pdfgen import canvas

def create_receipt(data, output_path):
    """Cria PDF do recibo"""
    
    order = data['order']
    items = data['items']
    
    # Criar documento
    doc = SimpleDocTemplate(
        output_path,
        pagesize=A4,
        rightMargin=20*mm,
        leftMargin=20*mm,
        topMargin=15*mm,
        bottomMargin=15*mm
    )
    
    # Estilos
    styles = getSampleStyleSheet()
    
    title_style = ParagraphStyle(
        'CustomTitle',
        parent=styles['Heading1'],
        fontSize=24,
        textColor=colors.HexColor('#00ff88'),
        spaceAfter=6,
        alignment=TA_CENTER,
        fontName='Helvetica-Bold'
    )
    
    subtitle_style = ParagraphStyle(
        'CustomSubtitle',
        parent=styles['Normal'],
        fontSize=10,
        textColor=colors.HexColor('#8b949e'),
        spaceAfter=20,
        alignment=TA_CENTER
    )
    
    heading_style = ParagraphStyle(
        'CustomHeading',
        parent=styles['Heading2'],
        fontSize=14,
        textColor=colors.HexColor('#00ff88'),
        spaceAfter=8,
        spaceBefore=12,
        fontName='Helvetica-Bold'
    )
    
    normal_style = ParagraphStyle(
        'CustomNormal',
        parent=styles['Normal'],
        fontSize=10,
        textColor=colors.HexColor('#c9d1d9'),
        spaceAfter=4
    )
    
    small_style = ParagraphStyle(
        'CustomSmall',
        parent=styles['Normal'],
        fontSize=8,
        textColor=colors.HexColor('#8b949e'),
        spaceAfter=2
    )
    
    # Story - conteúdo do PDF
    story = []
    
    # ========================================
    # CABEÇALHO
    # ========================================
    story.append(Paragraph("VISIONGREEN", title_style))
    story.append(Paragraph("Marketplace Ecológico de Moçambique", subtitle_style))
    story.append(Spacer(1, 5*mm))
    
    # Linha separadora
    story.append(Spacer(1, 2*mm))
    
    # ========================================
    # TÍTULO DO RECIBO
    # ========================================
    receipt_title = ParagraphStyle(
        'ReceiptTitle',
        parent=styles['Heading1'],
        fontSize=18,
        textColor=colors.HexColor('#ffffff'),
        alignment=TA_CENTER,
        fontName='Helvetica-Bold',
        spaceAfter=10
    )
    story.append(Paragraph(f"RECIBO DE COMPRA", receipt_title))
    story.append(Paragraph(f"Nº {order['order_number']}", subtitle_style))
    story.append(Spacer(1, 5*mm))
    
    # ========================================
    # INFORMAÇÕES DA EMPRESA
    # ========================================
    story.append(Paragraph("VENDEDOR", heading_style))
    
    company_data = [
        [Paragraph("<b>Empresa:</b>", normal_style), Paragraph(order['company_name'], normal_style)],
        [Paragraph("<b>NUIT:</b>", normal_style), Paragraph(order['company_nuit'] or 'N/A', normal_style)],
        [Paragraph("<b>Email:</b>", normal_style), Paragraph(order['company_email'], normal_style)],
        [Paragraph("<b>Telefone:</b>", normal_style), Paragraph(order['company_tel'], normal_style)],
    ]
    
    company_table = Table(company_data, colWidths=[40*mm, 120*mm])
    company_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#161b22')),
        ('TEXTCOLOR', (0, 0), (-1, -1), colors.HexColor('#c9d1d9')),
        ('ALIGN', (0, 0), (0, -1), 'LEFT'),
        ('ALIGN', (1, 0), (1, -1), 'LEFT'),
        ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
        ('FONTSIZE', (0, 0), (-1, -1), 9),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#30363d'))
    ]))
    story.append(company_table)
    story.append(Spacer(1, 5*mm))
    
    # ========================================
    # INFORMAÇÕES DO CLIENTE
    # ========================================
    story.append(Paragraph("CLIENTE", heading_style))
    
    customer_data = [
        [Paragraph("<b>Nome:</b>", normal_style), Paragraph(order['customer_name'], normal_style)],
        [Paragraph("<b>Email:</b>", normal_style), Paragraph(order['customer_email'], normal_style)],
        [Paragraph("<b>Telefone:</b>", normal_style), Paragraph(order['customer_phone'] or 'N/A', normal_style)],
    ]
    
    if order.get('shipping_address'):
        customer_data.append([
            Paragraph("<b>Endereço:</b>", normal_style), 
            Paragraph(order['shipping_address'], normal_style)
        ])
    
    customer_table = Table(customer_data, colWidths=[40*mm, 120*mm])
    customer_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#161b22')),
        ('TEXTCOLOR', (0, 0), (-1, -1), colors.HexColor('#c9d1d9')),
        ('ALIGN', (0, 0), (0, -1), 'LEFT'),
        ('ALIGN', (1, 0), (1, -1), 'LEFT'),
        ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
        ('FONTSIZE', (0, 0), (-1, -1), 9),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#30363d'))
    ]))
    story.append(customer_table)
    story.append(Spacer(1, 5*mm))
    
    # ========================================
    # DETALHES DO PEDIDO
    # ========================================
    story.append(Paragraph("DETALHES DO PEDIDO", heading_style))
    
    order_date = datetime.strptime(order['order_date'], '%Y-%m-%d %H:%M:%S')
    order_date_formatted = order_date.strftime('%d/%m/%Y às %H:%M')
    
    payment_methods = {
        'mpesa': 'M-Pesa',
        'emola': 'E-Mola',
        'visa': 'Visa',
        'mastercard': 'Mastercard',
        'manual': 'Pagamento Manual'
    }
    
    payment_status_pt = {
        'pendente': 'Pendente',
        'pago': 'Pago',
        'parcial': 'Parcial',
        'reembolsado': 'Reembolsado'
    }
    
    order_status_pt = {
        'pendente': 'Pendente',
        'confirmado': 'Confirmado',
        'processando': 'Em Processamento',
        'enviado': 'Enviado',
        'entregue': 'Entregue',
        'cancelado': 'Cancelado'
    }
    
    order_info_data = [
        [Paragraph("<b>Data do Pedido:</b>", normal_style), Paragraph(order_date_formatted, normal_style)],
        [Paragraph("<b>Status:</b>", normal_style), Paragraph(order_status_pt.get(order['status'], order['status']), normal_style)],
        [Paragraph("<b>Método de Pagamento:</b>", normal_style), Paragraph(payment_methods.get(order['payment_method'], order['payment_method']), normal_style)],
        [Paragraph("<b>Status Pagamento:</b>", normal_style), Paragraph(payment_status_pt.get(order['payment_status'], order['payment_status']), normal_style)],
    ]
    
    order_info_table = Table(order_info_data, colWidths=[50*mm, 110*mm])
    order_info_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#161b22')),
        ('TEXTCOLOR', (0, 0), (-1, -1), colors.HexColor('#c9d1d9')),
        ('ALIGN', (0, 0), (0, -1), 'LEFT'),
        ('ALIGN', (1, 0), (1, -1), 'LEFT'),
        ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
        ('FONTSIZE', (0, 0), (-1, -1), 9),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#30363d'))
    ]))
    story.append(order_info_table)
    story.append(Spacer(1, 7*mm))
    
    # ========================================
    # ITENS DO PEDIDO
    # ========================================
    story.append(Paragraph("ITENS DO PEDIDO", heading_style))
    
    items_data = [
        [
            Paragraph("<b>Produto</b>", normal_style),
            Paragraph("<b>Qtd</b>", normal_style),
            Paragraph("<b>Preço Unit.</b>", normal_style),
            Paragraph("<b>Desconto</b>", normal_style),
            Paragraph("<b>Total</b>", normal_style)
        ]
    ]
    
    for item in items:
        product_name = item['product_name'] or item.get('current_product_name', 'Produto não encontrado')
        items_data.append([
            Paragraph(product_name, normal_style),
            Paragraph(str(item['quantity']), normal_style),
            Paragraph(f"{float(item['unit_price']):.2f}".replace('.', ','), normal_style),
            Paragraph(f"{float(item.get('discount', 0)):.2f}".replace('.', ','), normal_style),
            Paragraph(f"{float(item['total']):.2f}".replace('.', ','), normal_style)
        ])
    
    items_table = Table(items_data, colWidths=[70*mm, 20*mm, 25*mm, 25*mm, 30*mm])
    items_table.setStyle(TableStyle([
        # Cabeçalho
        ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#00ff88')),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.black),
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('FONTSIZE', (0, 0), (-1, 0), 10),
        ('ALIGN', (0, 0), (-1, 0), 'CENTER'),
        
        # Corpo
        ('BACKGROUND', (0, 1), (-1, -1), colors.HexColor('#161b22')),
        ('TEXTCOLOR', (0, 1), (-1, -1), colors.HexColor('#c9d1d9')),
        ('FONTNAME', (0, 1), (-1, -1), 'Helvetica'),
        ('FONTSIZE', (0, 1), (-1, -1), 9),
        ('ALIGN', (1, 1), (1, -1), 'CENTER'),
        ('ALIGN', (2, 1), (-1, -1), 'RIGHT'),
        
        # Grid
        ('GRID', (0, 0), (-1, -1), 1, colors.HexColor('#30363d')),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
    ]))
    story.append(items_table)
    story.append(Spacer(1, 5*mm))
    
    # ========================================
    # TOTAIS
    # ========================================
    totals_data = []
    
    if float(order.get('subtotal', 0)) > 0:
        totals_data.append([
            Paragraph("<b>Subtotal:</b>", normal_style),
            Paragraph(f"{float(order['subtotal']):.2f} {order['currency']}".replace('.', ','), normal_style)
        ])
    
    if float(order.get('discount', 0)) > 0:
        totals_data.append([
            Paragraph("<b>Desconto:</b>", normal_style),
            Paragraph(f"- {float(order['discount']):.2f} {order['currency']}".replace('.', ','), normal_style)
        ])
    
    if float(order.get('shipping_cost', 0)) > 0:
        totals_data.append([
            Paragraph("<b>Frete:</b>", normal_style),
            Paragraph(f"{float(order['shipping_cost']):.2f} {order['currency']}".replace('.', ','), normal_style)
        ])
    
    if float(order.get('tax', 0)) > 0:
        totals_data.append([
            Paragraph("<b>Taxa:</b>", normal_style),
            Paragraph(f"{float(order['tax']):.2f} {order['currency']}".replace('.', ','), normal_style)
        ])
    
    total_style = ParagraphStyle(
        'TotalStyle',
        parent=normal_style,
        fontSize=14,
        textColor=colors.HexColor('#00ff88'),
        fontName='Helvetica-Bold'
    )
    
    totals_data.append([
        Paragraph("<b>TOTAL:</b>", total_style),
        Paragraph(f"{float(order['total']):.2f} {order['currency']}".replace('.', ','), total_style)
    ])
    
    totals_table = Table(totals_data, colWidths=[120*mm, 50*mm])
    totals_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -2), colors.HexColor('#161b22')),
        ('BACKGROUND', (0, -1), (-1, -1), colors.HexColor('#0d1117')),
        ('TEXTCOLOR', (0, 0), (-1, -1), colors.HexColor('#c9d1d9')),
        ('ALIGN', (0, 0), (0, -1), 'RIGHT'),
        ('ALIGN', (1, 0), (1, -1), 'RIGHT'),
        ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
        ('FONTSIZE', (0, 0), (-1, -2), 10),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 6),
        ('LINEABOVE', (0, -1), (-1, -1), 2, colors.HexColor('#00ff88')),
    ]))
    story.append(totals_table)
    story.append(Spacer(1, 10*mm))
    
    # ========================================
    # RODAPÉ
    # ========================================
    footer_style = ParagraphStyle(
        'Footer',
        parent=styles['Normal'],
        fontSize=8,
        textColor=colors.HexColor('#8b949e'),
        alignment=TA_CENTER,
        spaceAfter=2
    )
    
    now = datetime.now().strftime('%d/%m/%Y às %H:%M')
    story.append(Paragraph(f"Recibo gerado em: {now}", footer_style))
    story.append(Paragraph("VisionGreen - Marketplace Ecológico de Moçambique", footer_style))
    story.append(Paragraph("Este é um documento válido e pode ser utilizado para fins fiscais.", footer_style))
    
    # Construir PDF
    doc.build(story)
    
    return True

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Uso: python3 generate_receipt.py <arquivo_json>")
        sys.exit(1)
    
    try:
        with open(sys.argv[1], 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        receipt_path = data['receipt_path']
        
        success = create_receipt(data, receipt_path)
        
        if success:
            print(f"Recibo gerado: {receipt_path}")
            sys.exit(0)
        else:
            print("Erro ao gerar recibo")
            sys.exit(1)
            
    except Exception as e:
        print(f"Erro: {str(e)}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        sys.exit(1)